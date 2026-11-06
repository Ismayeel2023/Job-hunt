<?php

namespace Tests\Feature;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use App\Models\Application;
use App\Models\Company;
use App\Models\Email;
use App\Models\InterviewQuestion;
use App\Models\InterviewRound;
use App\Models\Job;
use App\Models\ResumeVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebUiTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public static function pages(): array
    {
        return [
            'dashboard' => ['/api/app/dashboard'],
            'jobs' => ['/api/app/jobs'],
            'board' => ['/api/app/applications'],
            'resumes' => ['/api/app/resumes'],
            'prep' => ['/api/app/prep'],
            'settings' => ['/api/app/settings'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_require_login_and_load_when_empty(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();

        $this->signIn()->getJson($uri)->assertOk();
    }

    public function test_dashboard_reports_response_rate_stages_and_top_unapplied_jobs(): void
    {
        $this->travelTo('2026-11-04 12:00:00');

        $silent = Application::factory()->create(['applied_at' => '2026-11-02 05:00:00']);
        $rejected = Application::factory()->create(['applied_at' => '2026-10-26 05:00:00']);
        $rejected->transitionTo(PipelineStatus::Interview, StatusEventSource::Manual);
        $rejected->transitionTo(PipelineStatus::Rejected, StatusEventSource::Manual);
        $ghosted = Application::factory()->create(['applied_at' => '2026-10-20 05:00:00']);
        $ghosted->transitionTo(PipelineStatus::Ghosted, StatusEventSource::Manual);
        $ghosted->job->update(['match_score' => 99]);

        $withNextStep = Application::factory()->create(['applied_at' => '2026-11-01 18:30:00', 'next_step' => 'Call', 'next_step_at' => '2026-11-06 05:00:00']);
        $closed = Application::factory()->create(['next_step' => 'Old', 'next_step_at' => '2026-11-05 05:00:00', 'applied_at' => '2026-09-01 05:00:00']);
        $closed->transitionTo(PipelineStatus::Withdrawn, StatusEventSource::Manual);

        foreach ([70, 95, 80, 60, 85, 90] as $score) {
            Job::factory()->create(['match_score' => $score, 'status' => PipelineStatus::Found]);
        }
        Job::factory()->create(['match_score' => null, 'status' => PipelineStatus::Found]);

        $this->signIn()->getJson('/api/app/dashboard')
            ->assertJsonPath('response.responded', 1)
            ->assertJsonPath('response.total', 5)
            ->assertJsonPath('response.rate', 20)
            ->assertJsonPath('thisWeek.count', 2)
            ->assertJsonPath('thisWeek.last_week', 1)
            ->assertJsonPath('stages.0.status', 'applied')
            ->assertJsonPath('stages.0.count', 5)
            ->assertJsonPath('stages.2.status', 'interview')
            ->assertJsonPath('stages.2.count', 1)
            ->assertJsonPath('stages.4.count', 0)
            ->assertJsonCount(1, 'nextSteps')
            ->assertJsonPath('nextSteps.0.id', $withNextStep->id)
            ->assertJsonCount(5, 'topJobs')
            ->assertJsonPath('topJobs.0.match_score', 95)
            ->assertJsonPath('topJobs.4.match_score', 70)
            ->assertJsonPath('totals.withdrawn', 1)
            ->assertJsonCount(7, 'week.days');
    }

    public function test_weekly_strip_uses_ist_for_days_and_the_locked_window(): void
    {
        // Monday 06:30 IST exactly.
        $this->travelTo('2026-11-02 01:00:00');
        Application::factory()->create(['applied_at' => '2026-11-01 18:30:00']);

        $this->signIn()->getJson('/api/app/dashboard')
            ->assertJsonPath('week.days.0.date', '2026-11-02')
            ->assertJsonPath('week.days.0.is_today', true)
            ->assertJsonPath('week.days.0.locked', ['start' => '06:30', 'end' => '11:30'])
            ->assertJsonPath('week.days.0.applied', 1)
            ->assertJsonPath('week.days.5.is_apply_day', true)
            ->assertJsonPath('week.days.6.is_apply_day', true)
            ->assertJsonPath('week.days.1.is_apply_day', false)
            ->assertJsonPath('week.is_locked', true)
            ->assertJsonPath('week.is_apply_day', false)
            ->assertJsonPath('week.now_minutes', 390);

        // Monday 11:30 IST exactly: the window has closed.
        $this->travelTo('2026-11-02 06:00:00');
        $this->getJson('/api/app/dashboard')->assertJsonPath('week.is_locked', false);
    }

    public function test_jobs_can_be_filtered_and_sorted(): void
    {
        $high = Job::factory()->create(['match_score' => 90, 'platform' => 'naukri', 'status' => 'queued', 'location' => 'Bengaluru']);
        $mid = Job::factory()->create(['match_score' => 65, 'platform' => 'linkedin', 'status' => 'found', 'location' => 'Pune']);
        $none = Job::factory()->create(['match_score' => null, 'platform' => 'linkedin', 'status' => 'found', 'location' => 'Pune']);
        $application = Application::factory()->for($mid)->create();

        $this->signIn();

        $ids = fn (string $query) => collect($this->getJson("/api/app/jobs?{$query}")->json('jobs.data'))->pluck('id')->all();

        $this->assertSame([$high->id, $mid->id, $none->id], $ids(''));
        $this->assertSame([$mid->id, $high->id, $none->id], $ids('direction=asc'));
        $this->assertSame([$high->id], $ids('platform=naukri'));
        $this->assertSame([$high->id], $ids('status=queued'));
        $this->assertSame([$mid->id], $ids('band=good'));
        $this->assertSame([$none->id], $ids('band=unscored'));
        $this->assertSame([$mid->id, $none->id], $ids('location=pun'));

        $this->getJson('/api/app/jobs?platform=naukri')->assertJsonMissingPath('jobs.data.0.jd_text');
        $this->getJson('/api/app/jobs?band=good')->assertJsonPath('jobs.data.0.applications.0.id', $application->id);
        $this->getJson('/api/app/jobs?band=nope')->assertUnprocessable()->assertJsonValidationErrors('band');
    }

    public function test_board_lists_applications_with_stage_reached(): void
    {
        $application = Application::factory()->create();
        $application->transitionTo(PipelineStatus::Interview, StatusEventSource::Manual);
        $application->transitionTo(PipelineStatus::Rejected, StatusEventSource::Manual);

        $this->signIn()->getJson('/api/app/applications')
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.status', 'rejected')
            ->assertJsonPath('applications.0.stage_reached', 'interview');
    }

    public function test_moving_a_card_records_a_manual_event_and_updates_the_job(): void
    {
        $application = Application::factory()->create(['next_step' => 'Keep']);

        $this->signIn()->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'online_test'])->assertSuccessful();

        $application->refresh();
        $event = $application->statusEvents()->sole();

        $this->assertSame(PipelineStatus::OnlineTest, $application->status);
        $this->assertSame('Keep', $application->next_step);
        $this->assertSame(PipelineStatus::OnlineTest, $application->job->status);
        $this->assertSame(PipelineStatus::Applied, $event->from_status);
        $this->assertSame(PipelineStatus::OnlineTest, $event->to_status);
        $this->assertSame(StatusEventSource::Manual, $event->source);
        $this->assertNull($event->evidence);
    }

    public function test_moving_a_card_from_the_board_returns_no_content_and_validation_errors_as_json(): void
    {
        $application = Application::factory()->create();

        $this->signIn();
        $this->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'hr'])->assertNoContent();
        $this->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'found'])->assertUnprocessable();

        $this->assertSame(PipelineStatus::Hr, $application->fresh()->status);
    }

    public function test_moving_a_card_to_the_same_or_an_invalid_status_records_nothing(): void
    {
        $application = Application::factory()->create();

        $this->signIn();
        $this->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'applied'])->assertSuccessful();
        $this->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'found'])->assertJsonValidationErrors('status');
        $this->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'nope'])->assertJsonValidationErrors('status');

        $this->assertSame(0, $application->statusEvents()->count());
        $this->assertSame(PipelineStatus::Applied, $application->fresh()->status);
    }

    public function test_moving_a_card_requires_login(): void
    {
        $application = Application::factory()->create();

        $this->patchJson("/api/app/applications/{$application->id}/status", ['status' => 'offer'])->assertUnauthorized();

        $this->assertSame(PipelineStatus::Applied, $application->fresh()->status);
    }

    public function test_application_detail_includes_resume_base_and_gmail_evidence(): void
    {
        $base = ResumeVersion::factory()->base()->create(['tex_source' => 'base tex']);
        $job = Job::factory()->create();
        $resume = ResumeVersion::factory()->for($job)->create(['tex_source' => 'tailored tex']);
        $application = Application::factory()->for($job)->create(['resume_version_id' => $resume->id]);
        $email = Email::factory()->create(['subject' => 'Interview invite']);
        $application->transitionTo(PipelineStatus::Interview, StatusEventSource::Gmail, ['email_id' => $email->id, 'evidence' => 'Invite', 'happened_at' => now()->subDay()]);
        $application->transitionTo(PipelineStatus::Rejected, StatusEventSource::Manual);

        $this->signIn()->getJson("/api/app/applications/{$application->id}")
            
            ->assertJsonPath('application.stage_reached', 'interview')
            ->assertJsonPath('application.resume_version.tex_source', 'tailored tex')
            ->assertJsonPath('baseResume.id', $base->id)
            ->assertJsonPath('baseResume.tex_source', 'base tex')
            ->assertJsonPath('application.status_events.0.to_status', 'rejected')
            ->assertJsonPath('application.status_events.1.source', 'gmail')
            ->assertJsonPath('application.status_events.1.email.subject', 'Interview invite');
    }

    public function test_resume_pdf_is_served_inline_or_as_a_download_to_logged_in_users_only(): void
    {
        Storage::fake('resumes');
        Storage::disk('resumes')->put('a.pdf', '%PDF-1.4 test');
        $resume = ResumeVersion::factory()->create(['pdf_path' => 'a.pdf', 'label' => 'Tailored for Acme']);

        $this->getJson("/api/app/resumes/{$resume->id}/pdf")->assertUnauthorized();

        $this->signIn();

        $inline = $this->getJson("/api/app/resumes/{$resume->id}/pdf");
        $inline->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));

        $download = $this->getJson("/api/app/resumes/{$resume->id}/pdf?download=1");
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('tailored-for-acme.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_a_resume_version_page_shows_its_changes_against_the_base(): void
    {
        $base = ResumeVersion::factory()->base()->create(['tex_source' => 'base tex']);
        $job = Job::factory()->for(Company::factory()->state(['name' => 'Acme']))->create(['title' => 'Dev']);
        $tailored = ResumeVersion::factory()->for($job)->create(['tex_source' => 'tailored tex', 'changes_summary' => ['added' => ['Summary'], 'removed' => [], 'reordered' => []]]);

        $this->getJson("/api/app/resumes/{$tailored->id}")->assertUnauthorized();

        $this->signIn()->getJson("/api/app/resumes/{$tailored->id}")
            
            ->assertJsonPath('version.tex_source', 'tailored tex')
            ->assertJsonPath('version.job.company.name', 'Acme')
            ->assertJsonPath('version.changes_summary.added', ['Summary'])
            ->assertJsonPath('baseResume.id', $base->id)
            ->assertJsonPath('baseResume.tex_source', 'base tex');

        $this->getJson("/api/app/resumes/{$base->id}")->assertJsonPath('version.is_base', true)->assertJsonPath('baseResume', null);
    }

    public function test_resume_pdf_returns_404_when_missing(): void
    {
        Storage::fake('resumes');
        $noPdf = ResumeVersion::factory()->create(['pdf_path' => null]);
        $missing = ResumeVersion::factory()->create(['pdf_path' => 'gone.pdf']);

        $this->signIn();
        $this->getJson("/api/app/resumes/{$noPdf->id}/pdf")->assertNotFound();
        $this->getJson("/api/app/resumes/{$missing->id}/pdf")->assertNotFound();
    }

    public function test_resumes_list_shows_job_company_and_keywords_without_the_tex_source(): void
    {
        $base = ResumeVersion::factory()->base()->create();
        $company = Company::factory()->create(['name' => 'Acme']);
        $job = Job::factory()->for($company)->create(['title' => 'Dev']);
        $tailored = ResumeVersion::factory()->for($job)->create(['keywords_covered' => ['Go']]);
        $application = Application::factory()->for($job)->create(['resume_version_id' => $tailored->id]);

        $this->signIn()->getJson('/api/app/resumes')
            ->assertJsonPath('versions.1.applications.0.company', 'Acme')
            ->assertJsonCount(2, 'versions')
            ->assertJsonPath('versions.0.id', $base->id)
            ->assertJsonPath('versions.1.company', 'Acme')
            ->assertJsonPath('versions.1.job_title', 'Dev')
            ->assertJsonPath('versions.1.keywords_covered', ['Go'])
            ->assertJsonPath('versions.1.applications.0.id', $application->id)
            ->assertJsonMissingPath('versions.1.tex_source');
    }

    public function test_prep_shows_the_selected_company_only(): void
    {
        $first = InterviewRound::factory()->create()->company;
        $second = InterviewQuestion::factory()->create(['practiced' => true])->company;
        Company::factory()->create();

        $this->signIn()->getJson("/api/app/prep?company={$second->id}")
            ->assertJsonCount(2, 'companies')
            ->assertJsonPath('company.id', $second->id)
            ->assertJsonCount(0, 'rounds')
            ->assertJsonCount(1, 'questions');

        $practiced = collect($this->getJson('/api/app/prep')->json('companies'))->firstWhere('id', $second->id);
        $this->assertSame(1, $practiced['practiced_count']);

        $this->getJson("/api/app/prep?company={$first->id}")->assertJsonCount(1, 'rounds')->assertJsonCount(0, 'questions');
    }

    public function test_questions_can_be_marked_practiced_and_annotated(): void
    {
        $question = InterviewQuestion::factory()->create(['question' => 'Keep me?', 'my_notes' => 'old']);

        $this->patchJson("/api/app/prep/questions/{$question->id}", ['practiced' => true])->assertUnauthorized();

        $this->signIn();
        $this->patchJson("/api/app/prep/questions/{$question->id}", ['practiced' => true, 'question' => 'Changed'])->assertNoContent();
        $this->assertTrue($question->fresh()->practiced);
        $this->assertSame('Keep me?', $question->fresh()->question);
        $this->assertSame('old', $question->fresh()->my_notes);

        $this->patchJson("/api/app/prep/questions/{$question->id}", ['my_notes' => 'new note'])->assertNoContent();
        $this->assertSame('new note', $question->fresh()->my_notes);
        $this->assertTrue($question->fresh()->practiced);
    }

    public function test_settings_masks_the_agent_token_and_lists_saved_email_accounts(): void
    {
        $token = 'abcdef0123456789secretvalue9876';
        config(['services.agent.token' => $token]);
        Email::factory()->create(['gmail_account' => 'a@example.com', 'processed' => false]);
        Email::factory()->create(['gmail_account' => 'a@example.com', 'processed' => true]);

        $response = $this->signIn()->getJson('/api/app/settings');

        $response->assertDontSee($token)
            ->assertJsonPath('agentToken.configured', true)
            ->assertJsonPath('agentToken.masked', str_repeat('•', 16).'9876')
            ->assertJsonPath('gmail.other_accounts.0.account', 'a@example.com')
            ->assertJsonPath('gmail.other_accounts.0.emails', 2)
            ->assertJsonPath('gmail.other_accounts.0.unprocessed', 1)
            ->assertJsonPath('rules.apply_days', ['saturday', 'sunday'])
            ->assertJsonPath('rules.locked_window.start', '06:30')
            ->assertJsonPath('rules.timezone', 'Asia/Kolkata');

        config(['services.agent.token' => '']);
        $this->getJson('/api/app/settings')->assertJsonPath('agentToken.configured', false)->assertJsonPath('agentToken.masked', null);
    }

    public function test_no_page_mentions_a_guaranteed_selection_metric(): void
    {
        $sources = collect(glob(base_path('../frontend/src/{Pages,Components,Layouts}/{,*/,*/*/}*.tsx'), GLOB_BRACE));

        $this->assertNotEmpty($sources);

        foreach ($sources as $file) {
            $this->assertDoesNotMatchRegularExpression('/guarantee/i', file_get_contents($file), $file);
        }
    }

    public function test_demo_seeder_creates_browsable_data_and_clear_removes_it_all(): void
    {
        Storage::fake('resumes');
        $this->seed(DatabaseSeeder::class);

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(7, Company::count());
        $this->assertSame(16, Job::count());
        $this->assertSame(9, Application::count());
        $this->assertTrue(Application::whereHas('statusEvents', fn ($query) => $query->where('source', 'gmail'))->exists());
        $this->assertStringContainsString('DEMO SAMPLE', ResumeVersion::where('is_base', true)->sole()->tex_source);

        foreach (Application::with('resumeVersion')->get() as $application) {
            $this->assertNotNull($application->resumeVersion);
            Storage::disk('resumes')->assertExists($application->resumeVersion->pdf_path);
            $this->assertStringStartsWith('%PDF-1.4', Storage::disk('resumes')->get($application->resumeVersion->pdf_path));
        }

        $this->assertSame(0, Artisan::call('jobhunt:demo-clear'));

        $this->assertSame(0, Company::count());
        $this->assertSame(0, Job::count());
        $this->assertSame(0, Application::count());
        $this->assertSame(0, Email::count());
        $this->assertSame([], Storage::disk('resumes')->allFiles());

        $base = ResumeVersion::sole();
        $this->assertTrue($base->is_base);
        $this->assertSame(DatabaseSeeder::BASE_PLACEHOLDER, $base->tex_source);
    }

    public function test_demo_seeder_never_overwrites_a_real_base_resume(): void
    {
        Storage::fake('resumes');
        $this->seed(DatabaseSeeder::class);
        ResumeVersion::where('is_base', true)->update(['tex_source' => 'my real resume']);

        $this->seed(DemoSeeder::class);
        Artisan::call('jobhunt:demo-clear');

        $this->assertSame('my real resume', ResumeVersion::where('is_base', true)->sole()->tex_source);
    }
}
