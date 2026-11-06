<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Email;
use App\Models\GmailAccount;
use App\Models\Job;
use App\Models\User;
use App\Services\Gmail\GoogleOAuth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array> Messages the fake Gmail API knows, by id. */
    private array $mailbox = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
            'services.google.redirect' => url('/settings/gmail/callback'),
        ]);
        Http::preventStrayRequests();
    }

    private function signIn(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function message(string $id, string $from, string $subject, string $snippet = 'Hello', string $date = '2026-11-05 10:00:00'): void
    {
        $this->mailbox[$id] = [
            'id' => $id,
            'threadId' => "t-{$id}",
            'snippet' => $snippet,
            'internalDate' => (string) (strtotime($date.' UTC') * 1000),
            'payload' => ['headers' => [
                ['name' => 'From', 'value' => $from],
                ['name' => 'Subject', 'value' => $subject],
            ]],
            'labelIds' => ['INBOX'],
        ];
    }

    /**
     * A fake Google: the token endpoint, and a Gmail API that returns every mailbox message for any search.
     */
    private function fakeGoogle(array $token = [], ?array $refresh = null): void
    {
        Http::fake(function (Request $request) use ($token, $refresh) {
            $url = $request->url();

            if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
                if (($request->data()['grant_type'] ?? null) === 'refresh_token') {
                    return Http::response(...($refresh ?? [['access_token' => 'fresh-access', 'expires_in' => 3600]]));
                }

                return Http::response($token + [
                    'access_token' => 'new-access',
                    'expires_in' => 3599,
                    'refresh_token' => 'new-refresh-token-secret',
                    'scope' => GoogleOAuth::SCOPE,
                    'token_type' => 'Bearer',
                ]);
            }

            if (str_starts_with($url, 'https://oauth2.googleapis.com/revoke')) {
                return Http::response([]);
            }

            if (str_starts_with($url, 'https://gmail.googleapis.com/gmail/v1/users/me/profile')) {
                return Http::response(['emailAddress' => 'Me@Gmail.example']);
            }

            if (preg_match('#/users/me/messages\?#', $url)) {
                return Http::response(['messages' => array_map(fn ($message) => ['id' => $message['id'], 'threadId' => $message['threadId']], array_values($this->mailbox))]);
            }

            if (preg_match('#/users/me/messages/([^?]+)\?format=metadata#', $url, $matches)) {
                return Http::response($this->mailbox[$matches[1]]);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });
    }

    /**
     * The message the Gmail sign-in hands to the frontend's Settings page.
     */
    private function notice($response): string
    {
        $location = $response->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith(config('app.frontend_url').'/settings?notice=', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return $query['notice'];
    }

    private function startConnect(): array
    {
        $location = $this->signIn()->get('/settings/gmail/connect')->assertRedirect()->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return [$location, $query];
    }

    // Connecting

    public function test_connect_asks_google_for_read_only_gmail_with_pkce_and_offline_access(): void
    {
        [$location, $query] = $this->startConnect();

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertSame('https://www.googleapis.com/auth/gmail.readonly', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(url('/settings/gmail/callback'), $query['redirect_uri']);
        $this->assertArrayNotHasKey('include_granted_scopes', $query);

        $pending = session('gmail_oauth');
        $this->assertSame($pending['state'], $query['state']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
    }

    public function test_connect_moves_to_the_redirect_host_first_so_the_session_matches(): void
    {
        $expected = url('/settings/gmail/connect');

        $this->signIn()->get('http://other.test/settings/gmail/connect')->assertRedirect($expected);
    }

    public function test_connect_needs_the_client_configured_and_a_login(): void
    {
        $this->get('/settings/gmail/connect')->assertRedirect('/login');

        config(['services.google.client_secret' => '']);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID', $this->notice($this->signIn()->get('/settings/gmail/connect')));
    }

    public function test_callback_stores_the_account_with_an_encrypted_refresh_token(): void
    {
        $this->fakeGoogle();
        [, $query] = $this->startConnect();

        $this->assertStringContainsString('Connected me@gmail.example', $this->notice($this->get('/settings/gmail/callback?code=abc&state='.$query['state'])));

        $account = GmailAccount::sole();
        $this->assertSame('me@gmail.example', $account->email);
        $this->assertSame('new-refresh-token-secret', $account->refresh_token);
        $this->assertStringNotContainsString('new-refresh-token-secret', DB::table('gmail_accounts')->value('refresh_token'));
        $this->assertStringNotContainsString('new-access', DB::table('gmail_accounts')->value('access_token'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['code'] === 'abc'
            && $request['code_verifier'] !== null
            && $request['redirect_uri'] === url('/settings/gmail/callback'));

        $this->getJson('/api/app/settings')
            ->assertDontSee('new-refresh-token-secret')
            ->assertDontSee('new-access')
            ->assertJsonPath('gmail.accounts.0.email', 'me@gmail.example');
    }

    public function test_callback_rejects_a_state_that_does_not_match(): void
    {
        $this->fakeGoogle();
        $this->startConnect();

        $this->assertStringContainsString('did not match', $this->notice($this->get('/settings/gmail/callback?code=abc&state=forged')));

        $this->assertSame(0, GmailAccount::count());
        Http::assertNothingSent();
    }

    public function test_callback_refuses_and_revokes_when_read_access_was_not_granted(): void
    {
        $this->fakeGoogle(['scope' => 'openid']);
        [, $query] = $this->startConnect();

        $this->assertStringContainsString('needs permission', $this->notice($this->get('/settings/gmail/callback?code=abc&state='.$query['state'])));

        $this->assertSame(0, GmailAccount::count());
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://oauth2.googleapis.com/revoke'));
    }

    public function test_callback_refuses_and_revokes_when_more_than_read_only_was_granted(): void
    {
        $this->fakeGoogle(['scope' => GoogleOAuth::SCOPE.' https://www.googleapis.com/auth/gmail.send']);
        [, $query] = $this->startConnect();

        $this->assertStringContainsString('more than read-only', $this->notice($this->get('/settings/gmail/callback?code=abc&state='.$query['state'])));

        $this->assertSame(0, GmailAccount::count());
    }

    public function test_callback_handles_the_user_cancelling(): void
    {
        [, $query] = $this->startConnect();

        $this->assertStringContainsString('not connected', $this->notice($this->get('/settings/gmail/callback?error=access_denied&state='.$query['state'])));

        $this->assertSame(0, GmailAccount::count());
    }

    // Syncing

    public function test_query_covers_platforms_application_companies_and_subject_words(): void
    {
        $acme = Company::factory()->create(['website' => 'https://www.acme.example/careers']);
        Application::factory()->for(Job::factory()->for($acme))->create();
        $freemail = Company::factory()->create(['website' => 'https://gmail.com']);
        Application::factory()->for(Job::factory()->for($freemail))->create();
        $odd = Company::factory()->create(['website' => 'https://bad host}.example']);
        Application::factory()->for(Job::factory()->for($odd))->create();
        Company::factory()->create(['website' => 'https://noapplications.example']);

        $this->fakeGoogle();
        GmailAccount::factory()->create();

        $this->artisan('jobhunt:sync-mail')->assertSuccessful();

        $queries = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $request) => str_contains($request->url(), '/users/me/messages?'))
            ->map(fn (Request $request) => $request->data()['q']);

        $this->assertCount(1, $queries);
        $query = $queries->first();

        $this->assertStringStartsWith('newer_than:30d {', $query);
        foreach (['naukri.com', 'linkedin.com', 'indeed.com', 'instahyre.com', 'wellfound.com', 'foundit.in', 'acme.example'] as $domain) {
            $this->assertStringContainsString("from:{$domain} ", $query);
        }
        foreach (['application', 'interview', 'assessment', 'shortlisted', 'offer', 'regret'] as $word) {
            $this->assertStringContainsString("subject:{$word}", $query);
        }
        $this->assertStringNotContainsString('gmail.com', $query);
        $this->assertStringNotContainsString('noapplications', $query);
        $this->assertStringNotContainsString('bad', $query);
    }

    public function test_sync_stores_only_metadata_of_matching_mail_and_links_it(): void
    {
        $acme = Company::factory()->create(['name' => 'Acme', 'website' => 'https://acme.example']);
        $acmeApplication = Application::factory()->for(Job::factory()->for($acme))->create();
        $zenith = Company::factory()->create(['name' => 'Zenith Labs', 'website' => 'https://zenith.example']);
        $zenithApplication = Application::factory()->for(Job::factory()->for($zenith))->create();

        $this->message('m1', 'Acme Talent <talent@mail.acme.example>', 'Next steps', 'We would like to talk &amp; meet');
        $this->message('m2', 'LinkedIn <jobs-noreply@linkedin.com>', 'Your application was sent to Zenith Labs');
        $this->message('m3', 'Mum <mum@family.example>', 'Interview tips from mum');
        $this->message('m4', 'Shop <deals@shop.example>', 'Weekend sale');
        $this->message('m5', 'Recruiter <r@agency.example>', 'Shortlisted for two roles at Acme and Zenith Labs');

        $this->fakeGoogle();
        $account = GmailAccount::factory()->create(['email' => 'me@gmail.example']);

        $this->artisan('jobhunt:sync-mail')
            ->expectsTable(['Account', 'Found', 'New', 'Linked', 'Skipped', 'Error'], [['me@gmail.example', 5, 4, 2, 1, '']])
            ->assertSuccessful();

        $this->assertNull(Email::where('gmail_message_id', 'm4')->first());

        $m1 = Email::where('gmail_message_id', 'm1')->sole();
        $this->assertSame('me@gmail.example', $m1->gmail_account);
        $this->assertSame('Acme Talent <talent@mail.acme.example>', $m1->from);
        $this->assertSame('We would like to talk & meet', $m1->snippet);
        $this->assertSame('2026-11-05 10:00:00', $m1->received_at->toDateTimeString());
        $this->assertSame($acmeApplication->id, $m1->matched_application_id);
        $this->assertNull($m1->classification);
        $this->assertFalse($m1->processed);

        $this->assertSame($zenithApplication->id, Email::where('gmail_message_id', 'm2')->value('matched_application_id'));
        $this->assertNull(Email::where('gmail_message_id', 'm3')->value('matched_application_id'));
        $this->assertNull(Email::where('gmail_message_id', 'm5')->value('matched_application_id'));

        $this->assertNotNull($account->fresh()->last_synced_at);
        // Every message fetch asks for headers only, never the body or attachments.
        $fetches = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->filter(fn ($url) => str_contains($url, '/users/me/messages/'));
        $this->assertCount(5, $fetches);
        $fetches->each(fn ($url) => $this->assertStringEndsWith('?format=metadata&metadataHeaders=From&metadataHeaders=Subject', $url));
    }

    public function test_sync_does_not_fetch_messages_it_already_has(): void
    {
        Email::factory()->create(['gmail_message_id' => 'm1']);
        $this->message('m1', 'jobs@naukri.com', 'Application viewed');
        $this->message('m2', 'jobs@naukri.com', 'Application viewed again');

        $this->fakeGoogle();
        GmailAccount::factory()->create();

        $this->artisan('jobhunt:sync-mail')->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/messages/m1?'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/messages/m2?'));
        $this->assertSame(2, Email::count());
    }

    public function test_a_company_with_several_applications_links_only_by_job_title(): void
    {
        $acme = Company::factory()->create(['name' => 'Acme', 'website' => 'https://acme.example']);
        Application::factory()->for(Job::factory()->for($acme)->state(['title' => 'Backend Developer']))->create();
        $frontend = Application::factory()->for(Job::factory()->for($acme)->state(['title' => 'Frontend Developer']))->create();

        $this->message('m1', 'hr@acme.example', 'Interview for Frontend Developer');
        $this->message('m2', 'hr@acme.example', 'Interview next week');

        $this->fakeGoogle();
        GmailAccount::factory()->create();
        $this->artisan('jobhunt:sync-mail')->assertSuccessful();

        $this->assertSame($frontend->id, Email::where('gmail_message_id', 'm1')->value('matched_application_id'));
        $this->assertNull(Email::where('gmail_message_id', 'm2')->value('matched_application_id'));
    }

    public function test_unlinked_unprocessed_email_is_linked_once_its_application_exists(): void
    {
        $email = Email::factory()->create(['from' => 'hr@acme.example', 'subject' => 'Hello', 'snippet' => 'x', 'matched_application_id' => null, 'processed' => false]);
        $processed = Email::factory()->create(['from' => 'hr@acme.example', 'subject' => 'Hello', 'snippet' => 'x', 'matched_application_id' => null, 'processed' => true]);
        $application = Application::factory()->for(Job::factory()->for(Company::factory()->state(['website' => 'https://acme.example'])))->create();

        $this->fakeGoogle();
        GmailAccount::factory()->create();
        $this->artisan('jobhunt:sync-mail')->assertSuccessful();

        $this->assertSame($application->id, $email->fresh()->matched_application_id);
        $this->assertNull($processed->fresh()->matched_application_id);
    }

    public function test_an_expired_access_token_is_refreshed(): void
    {
        $this->fakeGoogle();
        $account = GmailAccount::factory()->create(['access_token' => 'old', 'access_token_expires_at' => now()->subMinute()]);

        $this->artisan('jobhunt:sync-mail')->assertSuccessful();

        $this->assertSame('fresh-access', $account->fresh()->access_token);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/users/me/messages?') && $request->hasHeader('Authorization', 'Bearer fresh-access'));
    }

    public function test_a_revoked_or_expired_connection_is_flagged_for_reconnecting(): void
    {
        $this->fakeGoogle(refresh: [['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400]);
        $account = GmailAccount::factory()->create(['access_token_expires_at' => now()->subMinute()]);

        $this->artisan('jobhunt:sync-mail')->assertFailed();

        $this->assertTrue($account->fresh()->needs_reconnect);

        // Flagged accounts are skipped until reconnected.
        $this->artisan('jobhunt:sync-mail')->expectsOutputToContain('No Gmail accounts to sync')->assertSuccessful();

        $this->signIn()->getJson('/api/app/settings')->assertJsonPath('gmail.accounts.0.needs_reconnect', true);
    }

    public function test_command_reports_missing_setup_and_an_unknown_account(): void
    {
        $this->fakeGoogle();
        $this->artisan('jobhunt:sync-mail --account=nobody@example.com')->assertFailed();

        config(['services.google.client_id' => '']);
        $this->artisan('jobhunt:sync-mail')->expectsOutputToContain('GOOGLE_CLIENT_ID')->assertFailed();
    }

    public function test_only_one_sync_runs_at_a_time(): void
    {
        $this->fakeGoogle();
        GmailAccount::factory()->create();
        $lock = Cache::lock('jobhunt:sync-mail', 60);
        $lock->get();

        $this->artisan('jobhunt:sync-mail')->expectsOutputToContain('already running')->assertSuccessful();
        $this->signIn()->postJson('/api/app/settings/gmail/sync')->assertJsonPath('message', fn ($status) => str_contains($status, 'already running'));

        $lock->release();
        Http::assertNothingSent();
    }

    public function test_sync_is_scheduled_hourly_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'jobhunt:sync-mail'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // Settings actions

    public function test_sync_now_button_syncs_and_reports(): void
    {
        $this->message('m1', 'jobs@naukri.com', 'Application viewed');
        $this->fakeGoogle();
        GmailAccount::factory()->create();

        $this->postJson('/api/app/settings/gmail/sync')->assertUnauthorized();

        $this->signIn()->postJson('/api/app/settings/gmail/sync')
            ->assertOk()
            ->assertJsonPath('message', 'Sync finished: 1 new email, 0 linked to an application.');
    }

    public function test_disconnect_revokes_access_and_keeps_saved_email(): void
    {
        $this->fakeGoogle();
        $account = GmailAccount::factory()->create(['email' => 'me@gmail.example', 'refresh_token' => 'refresh-to-revoke']);
        Email::factory()->create(['gmail_account' => 'me@gmail.example']);

        $this->signIn()->deleteJson("/api/app/settings/gmail/{$account->id}")->assertOk()->assertJsonPath('message', fn ($message) => str_contains($message, 'Disconnected'));

        $this->assertModelMissing($account);
        $this->assertSame(1, Email::count());
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://oauth2.googleapis.com/revoke') && $request['token'] === 'refresh-to-revoke');
    }

    public function test_settings_separates_connected_accounts_from_other_saved_email(): void
    {
        GmailAccount::factory()->create(['email' => 'me@gmail.example']);
        Email::factory()->create(['gmail_account' => 'me@gmail.example', 'processed' => false]);
        Email::factory()->create(['gmail_account' => 'old@example.com']);

        $this->signIn()->getJson('/api/app/settings')
            ->assertJsonPath('gmail.configured', true)
            ->assertJsonPath('gmail.redirect_uri', url('/settings/gmail/callback'))
            ->assertJsonPath('gmail.accounts.0.emails', 1)
            ->assertJsonPath('gmail.accounts.0.unprocessed', 1)
            ->assertJsonCount(1, 'gmail.other_accounts')
            ->assertJsonPath('gmail.other_accounts.0.account', 'old@example.com');
    }
}
