<?php

namespace Tests\Feature;

use App\Enums\ApplyRunStatus;
use App\Models\Application;
use App\Models\ApplyRun;
use App\Models\Company;
use App\Models\Job;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Support\CandidateDetails;
use App\Support\Salary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApplyRunTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-agent-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.token' => self::TOKEN]);
    }

    private function signIn(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function tailoredJob(array $attributes = []): Job
    {
        $job = Job::factory()->for(Company::factory()->state(['name' => 'Acme']))->create($attributes);
        ResumeVersion::factory()->for($job)->create(['pdf_path' => 'acme.pdf']);

        return $job;
    }

    private function runJson(string $command, array $parameters): array
    {
        $this->assertSame(0, Artisan::call($command, $parameters));

        return json_decode(Artisan::output(), true);
    }

    private function agent(string $method, string $uri, array $data = [])
    {
        return $this->withToken(self::TOKEN)->json($method, "/api/agent/{$uri}", $data);
    }

    // Job page

    public function test_job_page_shows_job_salary_run_and_worker(): void
    {
        $job = $this->tailoredJob(['salary_text' => '₹15,000 - ₹30,000 a month']);
        CandidateDetails::put(['current_ctc_lpa' => 2.4]);

        $this->getJson("/api/app/jobs/{$job->id}")->assertUnauthorized();

        $this->signIn()->getJson("/api/app/jobs/{$job->id}")
            
            ->assertJsonPath('job.id', $job->id)
            ->assertJsonPath('job.company.name', 'Acme')
            ->assertJsonCount(1, 'resumeVersions')
            ->assertJsonPath('application', null)
            ->assertJsonPath('salary.posted', [1.8, 3.6])
            ->assertJsonPath('salary.suggested', [2.9, 3.4])
            ->assertJsonPath('run', null)
            ->assertJsonPath('worker.online', false)
            ->assertJsonCount(8, 'steps');
    }

    // Apply button

    public function test_apply_button_creates_a_requested_run(): void
    {
        $job = $this->tailoredJob();

        $this->postJson("/api/app/jobs/{$job->id}/apply")->assertUnauthorized();

        $this->signIn()->postJson("/api/app/jobs/{$job->id}/apply")->assertSuccessful();
        $run = ApplyRun::sole();

        $this->assertSame(ApplyRunStatus::Requested, $run->status);
        $this->assertSame($job->id, $run->job_id);

        // A second click while one is active does nothing.
        $this->postJson("/api/app/jobs/{$job->id}/apply")->assertJsonPath('message', 'Claude is already working on this job.');
        $this->assertSame(1, ApplyRun::count());
    }

    public function test_apply_button_needs_a_tailored_resume_and_no_existing_application(): void
    {
        $untailored = Job::factory()->create();
        $applied = $this->tailoredJob();
        Application::factory()->for($applied)->create();

        $this->signIn();
        $this->postJson("/api/app/jobs/{$untailored->id}/apply")->assertJsonPath('message', fn ($status) => str_contains($status, '/tailor'));
        $this->postJson("/api/app/jobs/{$applied->id}/apply")->assertJsonPath('message', 'You have already applied to this job.');

        $this->assertSame(0, ApplyRun::count());
    }

    // Worker commands

    public function test_apply_next_claims_the_oldest_request_and_prints_what_claude_needs(): void
    {
        $job = $this->tailoredJob(['title' => 'Dev']);
        CandidateDetails::put(['notice_period' => '30 days']);
        CandidateDetails::rememberAnswer('Willing to work nights?', 'No');
        $run = $job->applyRuns()->create(['status' => ApplyRunStatus::Requested]);

        $payload = $this->runJson('jobhunt:apply-next', ['--wait' => 0]);

        $this->assertSame($run->id, $payload['run_id']);
        $this->assertSame('Dev', $payload['job']['title']);
        $this->assertSame('Acme', $payload['job']['company']);
        $this->assertStringEndsWith('acme.pdf', $payload['resume']['pdf_file']);
        $this->assertSame('30 days', $payload['candidate']['notice_period']);
        $this->assertSame('Willing to work nights?', $payload['saved_answers'][0]['question']);

        $this->assertSame(ApplyRunStatus::Running, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->claimed_at);

        // Nothing left to claim, and the worker shows as online.
        $this->assertSame(['run_id' => null], $this->runJson('jobhunt:apply-next', ['--wait' => 0]));
        $this->signIn()->getJson("/api/app/jobs/{$job->id}")->assertJsonPath('worker.online', true);
    }

    public function test_apply_wait_reports_answers_approval_and_cancellation(): void
    {
        $run = $this->tailoredJob()->applyRuns()->create(['status' => ApplyRunStatus::AwaitingConfirmation, 'summary' => [['label' => 'Job', 'value' => 'Dev']]]);

        $waiting = $this->runJson('jobhunt:apply-wait', ['run' => $run->id, '--wait' => 0]);
        $this->assertFalse($waiting['approved']);
        $this->assertTrue($waiting['still_waiting']);

        $this->signIn()->postJson("/api/app/apply-runs/{$run->id}/confirm")->assertSuccessful();

        $approved = $this->runJson('jobhunt:apply-wait', ['run' => $run->id, '--wait' => 0]);
        $this->assertTrue($approved['approved']);
        $this->assertFalse($approved['still_waiting']);

        $this->postJson("/api/app/apply-runs/{$run->id}/cancel")->assertSuccessful();

        $cancelled = $this->runJson('jobhunt:apply-wait', ['run' => $run->id, '--wait' => 0]);
        $this->assertFalse($cancelled['approved']);
        $this->assertTrue($cancelled['cancelled']);
    }

    // Agent progress reports

    public function test_agent_reports_steps_and_notes(): void
    {
        $run = $this->tailoredJob()->applyRuns()->create(['status' => ApplyRunStatus::Running]);

        $this->agent('patch', "apply-runs/{$run->id}", ['step' => 'fill_form', 'note' => 'Filling in Notice period'])
            ->assertOk()
            ->assertJsonPath('step', 'fill_form')
            ->assertJsonPath('log.0.message', 'Filling in Notice period');

        $this->agent('patch', "apply-runs/{$run->id}", ['step' => 'nope', 'status' => 'requested'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['step', 'status']);

        $this->withoutToken()->patchJson("/api/agent/apply-runs/{$run->id}", ['note' => 'x'])->assertUnauthorized();
    }

    public function test_questions_are_asked_answered_on_the_page_and_remembered(): void
    {
        $run = $this->tailoredJob()->applyRuns()->create(['status' => ApplyRunStatus::Running]);

        $this->agent('patch', "apply-runs/{$run->id}", ['step' => 'screening', 'questions' => ['Expected CTC?', 'Notice period?']])
            ->assertOk()
            ->assertJsonPath('status', 'needs_answer')
            ->assertJsonCount(2, 'questions');

        $ids = collect($run->fresh()->questions)->pluck('id');

        $this->signIn();
        $this->postJson("/api/app/apply-runs/{$run->id}/answers", ['answers' => [$ids[0] => '5 LPA']])->assertSuccessful();
        $this->assertSame(ApplyRunStatus::NeedsAnswer, $run->fresh()->status);

        $this->postJson("/api/app/apply-runs/{$run->id}/answers", ['answers' => [$ids[1] => '30 days']])->assertSuccessful();
        $this->assertSame(ApplyRunStatus::Running, $run->fresh()->status);
        $this->assertSame(['5 LPA', '30 days'], collect($run->fresh()->questions)->pluck('answer')->all());

        $this->assertSame(
            [['question' => 'Expected CTC?', 'answer' => '5 LPA'], ['question' => 'Notice period?', 'answer' => '30 days']],
            CandidateDetails::savedAnswers(),
        );

        $this->assertFalse($this->runJson('jobhunt:apply-wait', ['run' => $run->id, '--wait' => 0])['still_waiting']);

        // Answers can't be posted when the run isn't waiting for them.
        $this->postJson("/api/app/apply-runs/{$run->id}/answers", ['answers' => [$ids[0] => 'changed']])->assertStatus(409);
    }

    public function test_the_agent_cannot_submit_without_the_users_approval(): void
    {
        $run = $this->tailoredJob()->applyRuns()->create(['status' => ApplyRunStatus::Running]);

        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'awaiting_confirmation'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('summary');

        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'awaiting_confirmation', 'step' => 'review', 'summary' => [['label' => 'Job', 'value' => 'Dev']]])
            ->assertOk();

        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'submitting'])->assertUnprocessable();
        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'submitted'])->assertUnprocessable();
        $this->agent('patch', "apply-runs/{$run->id}", ['confirmed_at' => now()->toIso8601String()])->assertOk();
        $this->assertNull($run->fresh()->confirmed_at);

        $this->signIn()->postJson("/api/app/apply-runs/{$run->id}/confirm")->assertSuccessful();

        $application = Application::factory()->for($run->job)->create();
        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'submitting', 'step' => 'submit'])->assertOk();
        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'submitted', 'step' => 'record', 'application_id' => $application->id])
            ->assertOk()
            ->assertJsonPath('status', 'submitted')
            ->assertJsonPath('application_id', $application->id);

        $this->assertNotNull($run->fresh()->finished_at);
    }

    public function test_confirm_only_works_while_waiting_for_approval(): void
    {
        $run = $this->tailoredJob()->applyRuns()->create(['status' => ApplyRunStatus::Running]);

        $this->postJson("/api/app/apply-runs/{$run->id}/confirm")->assertUnauthorized();
        $this->signIn()->postJson("/api/app/apply-runs/{$run->id}/confirm")->assertStatus(409);

        $this->assertNull($run->fresh()->confirmed_at);
    }

    public function test_a_cancelled_run_cannot_be_continued_by_the_agent(): void
    {
        $run = $this->tailoredJob()->applyRuns()->create(['status' => ApplyRunStatus::Running]);

        $this->signIn()->postJson("/api/app/apply-runs/{$run->id}/cancel")->assertSuccessful();
        $this->assertSame(ApplyRunStatus::Cancelled, $run->fresh()->status);

        $this->agent('patch', "apply-runs/{$run->id}", ['note' => 'Still going'])->assertStatus(409);
        $this->postJson("/api/app/apply-runs/{$run->id}/cancel")->assertStatus(409);
    }

    public function test_refused_runs_finish_and_allow_trying_again(): void
    {
        $job = $this->tailoredJob();
        $run = $job->applyRuns()->create(['status' => ApplyRunStatus::Running]);

        $this->agent('patch', "apply-runs/{$run->id}", ['status' => 'refused', 'step' => 'check_rules', 'note' => 'Applying runs on Saturday and Sunday (IST).'])
            ->assertOk()
            ->assertJsonPath('status', 'refused');

        $this->signIn()->postJson("/api/app/jobs/{$job->id}/apply")->assertSuccessful();
        $this->assertSame(2, $job->applyRuns()->count());
    }

    // Your details and salary

    public function test_details_are_saved_from_settings_and_answers_can_be_forgotten(): void
    {
        $this->patchJson('/api/app/settings/candidate', ['current_ctc_lpa' => '3.6'])->assertUnauthorized();

        $this->signIn()->patchJson('/api/app/settings/candidate', [
            'current_ctc_lpa' => '3.6',
            'expected_ctc_lpa' => '',
            'notice_period' => '30 days',
            'relocate' => 'Yes',
        ])->assertOk()->assertJsonStructure(['message', 'candidate']);

        $this->assertSame(['current_ctc_lpa' => 3.6, 'expected_ctc_lpa' => null, 'notice_period' => '30 days', 'relocate' => 'Yes'], CandidateDetails::get());

        $this->patchJson('/api/app/settings/candidate', ['current_ctc_lpa' => 'lots'])->assertJsonValidationErrors('current_ctc_lpa');

        CandidateDetails::rememberAnswer('A?', '1');
        CandidateDetails::rememberAnswer('B?', '2');
        $this->deleteJson('/api/app/settings/answers/0')->assertSuccessful();
        $this->assertSame([['question' => 'B?', 'answer' => '2']], CandidateDetails::savedAnswers());

        $this->getJson('/api/app/settings')
            ->assertJsonPath('candidate.current_ctc_lpa', 3.6)
            ->assertJsonPath('savedAnswers.0.question', 'B?');
    }

    public function test_salary_text_is_read_as_a_yearly_range(): void
    {
        $this->assertSame([383000, 1650000], Salary::parse('₹3,83,000 - ₹16,50,000 a year'));
        $this->assertSame([180000, 360000], Salary::parse('₹15,000 - ₹30,000 a month'));
        $this->assertSame([1200000, 1800000], Salary::parse('12-18 LPA'));
        $this->assertSame([600000, 600000], Salary::parse('From ₹50,000 a month'));
        $this->assertNull(Salary::parse('Not disclosed'));
        $this->assertNull(Salary::parse('₹13,000 - ₹20,000 an hour'));
        $this->assertNull(Salary::parse(null));
    }

    public function test_salary_suggestions_stay_inside_the_posted_range(): void
    {
        $range = '₹15,000 - ₹30,000 a month';

        $this->assertSame([2.7, 3.6], Salary::advice($range, null, null)['suggested']);
        $this->assertSame([2.9, 3.4], Salary::advice($range, 2.4, null)['suggested']);
        $this->assertSame([3.6, 3.6], Salary::advice($range, 3.0, null)['suggested']);

        $above = Salary::advice($range, 4.0, null);
        $this->assertSame([3.6, 3.6], $above['suggested']);
        $this->assertNotEmpty($above['notes']);

        $this->assertNotEmpty(Salary::advice($range, null, 5.0)['notes']);
        $this->assertSame([5.0, 5.0], Salary::advice('Not disclosed', 3.0, 5.0)['suggested']);
        $this->assertSame([3.6, 4.2], Salary::advice(null, 3.0, null)['suggested']);
        $this->assertNull(Salary::advice(null, null, null)['suggested']);

        // A very wide band (top at least 2.5x the bottom) suggests its lower third, not its top.
        $wide = Salary::advice('₹3,83,000 - ₹16,50,000 a year', null, null);
        $this->assertSame([3.8, 8.1], $wide['suggested']);
        $this->assertNotEmpty($wide['notes']);
        $this->assertSame([3.8, 4.5], Salary::advice('₹3,83,000 - ₹16,50,000 a year', 3.2, null)['suggested']);
    }

    public function test_dashboard_lists_salary_suggestions_for_queued_and_applied_jobs(): void
    {
        $queued = Job::factory()->for(Company::factory()->state(['name' => 'Queued Co']))->create(['status' => 'queued', 'salary_text' => '12-18 LPA', 'match_score' => 90]);
        $applied = Application::factory()->create()->job;
        $applied->update(['salary_text' => null, 'match_score' => 50]);
        Job::factory()->create(['status' => 'found']);
        $rejected = Application::factory()->create()->job;
        $rejected->update(['status' => 'rejected']);

        $this->signIn()->getJson('/api/app/dashboard')
            ->assertJsonCount(2, 'salaries')
            ->assertJsonPath('salaries.0.company', 'Queued Co')
            ->assertJsonPath('salaries.0.posted', [12, 18])
            ->assertJsonPath('salaries.0.suggested', [15, 18])
            ->assertJsonPath('salaries.1.id', $applied->id)
            ->assertJsonPath('salaries.1.suggested', null);
    }
}
