<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailException;
use App\Services\Gmail\GoogleOAuth;
use App\Services\Gmail\MailSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GmailController extends Controller
{
    public function __construct(private GoogleOAuth $oauth)
    {
    }

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->oauth->configured()) {
            return $this->done('Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env before connecting Gmail.');
        }

        // The state lives in the session cookie, so sign-in has to start on the same host Google returns to.
        $origin = rtrim(Str::before($this->oauth->redirectUri(), '/settings/'), '/');

        if ($request->getSchemeAndHttpHost() !== $origin) {
            return redirect()->away($origin.$request->getRequestUri());
        }

        $state = Str::random(40);
        $verifier = Str::random(96);
        $request->session()->put('gmail_oauth', ['state' => $state, 'verifier' => $verifier]);

        return redirect()->away($this->oauth->authorizationUrl($state, $verifier));
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('gmail_oauth');

        if ($request->filled('error')) {
            return $this->done('Gmail was not connected ('.Str::limit((string) $request->query('error'), 60).').');
        }

        if (! $pending || ! hash_equals($pending['state'], (string) $request->query('state'))) {
            return $this->done('That Gmail sign-in did not match this browser session. Click Connect again.');
        }

        try {
            $tokens = $this->oauth->exchangeCode((string) $request->query('code'), $pending['verifier']);
        } catch (GmailException $exception) {
            return $this->done($exception->getMessage());
        }

        $scopes = array_values(array_filter(explode(' ', $tokens['scope'] ?? '')));

        if ($scopes !== [GoogleOAuth::SCOPE]) {
            $this->oauth->revoke($tokens['access_token']);

            return $this->done(in_array(GoogleOAuth::SCOPE, $scopes, true)
                ? 'Google granted more than read-only access, so JobHunt disconnected it again.'
                : 'JobHunt needs permission to read your email. Connect again and allow it on the Google screen.');
        }

        if (empty($tokens['refresh_token'])) {
            $this->oauth->revoke($tokens['access_token']);

            return $this->done('Google did not return a long-lived token. Connect again.');
        }

        try {
            $email = strtolower((new GmailClient($tokens['access_token']))->profileEmail());
        } catch (GmailException $exception) {
            $this->oauth->revoke($tokens['refresh_token']);

            return $this->done($exception->getMessage());
        }

        GmailAccount::updateOrCreate(['email' => $email], [
            'refresh_token' => $tokens['refresh_token'],
            'access_token' => $tokens['access_token'],
            'access_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'scopes' => GoogleOAuth::SCOPE,
            'needs_reconnect' => false,
            'last_error' => null,
        ]);

        return $this->done("Connected {$email}. Click Sync now to fetch job-related email.");
    }

    public function sync(MailSync $sync): JsonResponse
    {
        $results = $sync->run();

        if ($results === null) {
            return $this->message('A sync is already running. Try again in a minute.');
        }

        if ($results === []) {
            return $this->message('No connected Gmail accounts to sync.');
        }

        $stored = array_sum(array_column($results, 'stored'));
        $linked = array_sum(array_column($results, 'linked'));
        $errors = array_filter(array_column($results, 'error'));

        return $this->message(
            "Sync finished: {$stored} new ".Str::plural('email', $stored).", {$linked} linked to an application."
            .($errors ? ' '.count($errors).' '.Str::plural('account', count($errors)).' had a problem; see below.' : '')
        );
    }

    public function destroy(GmailAccount $gmailAccount): JsonResponse
    {
        $this->oauth->revoke($gmailAccount->refresh_token);
        $gmailAccount->delete();

        return $this->message("Disconnected {$gmailAccount->email}. Email already saved from it was kept.");
    }

    private function message(string $status): JsonResponse
    {
        return response()->json(['message' => $status]);
    }

    /**
     * Ends the Google sign-in on the frontend's Settings page, with the outcome to show there.
     */
    private function done(string $status): RedirectResponse
    {
        return redirect()->away(rtrim(config('app.frontend_url'), '/').'/settings?notice='.rawurlencode($status));
    }
}
