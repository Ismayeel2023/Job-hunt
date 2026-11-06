<?php

namespace App\Services\Gmail;

use App\Models\GmailAccount;
use Illuminate\Support\Facades\Http;

class GoogleOAuth
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect');
    }

    /**
     * Only gmail.readonly is requested, with PKCE, and prompt=consent so Google always returns a refresh token.
     */
    public function authorizationUrl(string $state, string $verifier): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * @return array{access_token: string, expires_in?: int, refresh_token?: string, scope?: string}
     */
    public function exchangeCode(string $code, string $verifier): array
    {
        $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new GmailException('Google did not accept the sign-in: '.($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()));
        }

        return $response->json();
    }

    /**
     * A valid access token for the account, refreshed when it is within a minute of expiring.
     */
    public function accessToken(GmailAccount $account): string
    {
        if ($account->access_token && $account->access_token_expires_at?->isAfter(now()->addMinute())) {
            return $account->access_token;
        }

        $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->json('error') === 'invalid_grant') {
            throw new ReconnectRequired('Google no longer accepts this connection. Connect the account again.');
        }

        if ($response->failed() || ! $response->json('access_token')) {
            throw new GmailException('Could not refresh Gmail access: '.($response->json('error_description') ?? 'HTTP '.$response->status()));
        }

        $account->update([
            'access_token' => $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $account->access_token;
    }

    /**
     * Best effort: the account is removed locally even if Google cannot be reached.
     */
    public function revoke(string $token): void
    {
        rescue(fn () => Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $token]), report: false);
    }
}
