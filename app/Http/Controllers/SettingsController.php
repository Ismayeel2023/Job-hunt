<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\GmailAccount;
use App\Services\Gmail\GoogleOAuth;
use App\Support\CandidateDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __invoke(Request $request, GoogleOAuth $oauth): Response
    {
        $token = (string) config('services.agent.token');

        $counts = Email::query()
            ->select('gmail_account', DB::raw('count(*) as emails'), DB::raw('sum(case when processed then 0 else 1 end) as unprocessed'), DB::raw('max(received_at) as last_received_at'))
            ->groupBy('gmail_account')
            ->orderBy('gmail_account')
            ->get()
            ->map(fn (Email $email) => [
                'account' => $email->gmail_account,
                'emails' => (int) $email->getAttribute('emails'),
                'unprocessed' => (int) $email->getAttribute('unprocessed'),
                'last_received_at' => $email->getAttribute('last_received_at') ? Carbon::parse($email->getAttribute('last_received_at')) : null,
            ])
            ->keyBy('account');

        $connected = GmailAccount::orderBy('email')->get();

        return Inertia::render('Settings/Index', [
            'gmail' => [
                'configured' => $oauth->configured(),
                'redirect_uri' => $oauth->redirectUri(),
                'accounts' => $connected->map(fn (GmailAccount $account) => [
                    'id' => $account->id,
                    'email' => $account->email,
                    'needs_reconnect' => $account->needs_reconnect,
                    'last_synced_at' => $account->last_synced_at,
                    'last_error' => $account->last_error,
                    'emails' => $counts[$account->email]['emails'] ?? 0,
                    'unprocessed' => $counts[$account->email]['unprocessed'] ?? 0,
                ]),
                'other_accounts' => $counts->except($connected->pluck('email')->all())->values(),
            ],
            'candidate' => CandidateDetails::get(),
            'savedAnswers' => CandidateDetails::savedAnswers(),
            'agentToken' => [
                'configured' => $token !== '',
                'masked' => $token === '' ? null : str_repeat('•', 16).substr($token, -4),
                // The API only accepts 127.0.0.1, so show that rather than whichever host this page was opened on.
                'endpoint' => 'http://127.0.0.1:'.$request->getPort().'/api/agent',
            ],
            'rules' => [
                ...config('jobhunt.apply_rules'),
                'timezone' => config('jobhunt.timezone'),
            ],
        ]);
    }
}
