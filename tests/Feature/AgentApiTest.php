<?php

namespace Tests\Feature;

use App\Enums\PipelineStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\Email;
use App\Models\InterviewQuestion;
use App\Models\InterviewRound;
use App\Models\Job;
use App\Models\ResumeVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-agent-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.token' => self::TOKEN]);
        $this->withToken(self::TOKEN);
    }

    public static function endpoints(): array
    {
        return [
            'upsert job' => ['post', '/api/agent/jobs'],
            'show job' => ['get', '/api/agent/jobs/1'],
            'list applications' => ['get', '/api/agent/applications'],
            'list companies' => ['get', '/api/agent/companies'],
            'store company' => ['post', '/api/agent/companies'],
            'update job' => ['patch', '/api/agent/jobs/1'],
            'list jobs' => ['get', '/api/agent/jobs'],
            'store resume version' => ['post', '/api/agent/resume-versions'],
            'store application' => ['post', '/api/agent/applications'],
            'store status' => ['post', '/api/agent/applications/1/status'],
            'store round' => ['post', '/api/agent/companies/1/rounds'],
            'store questions' => ['post', '/api/agent/companies/1/questions'],
            'list emails' => ['get', '/api/agent/emails'],
            'update email' => ['patch', '/api/agent/emails/1'],
            'summary' => ['get', '/api/agent/summary'],
        ];
    }

    // Auth

    #[DataProvider('endpoints')]
    public function test_every_endpoint_requires_the_token(string $method, string $uri): void
    {
        $this->withoutToken()->json($method, $uri)->assertUnauthorized();
        $this->withToken('wrong')->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_every_endpoint_rejects_non_local_requests(string $method, string $uri): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->json($method, $uri)
            ->assertForbidden();
    }

    public function test_forwarded_header_cannot_spoof_localhost(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->withHeader('X-Forwarded-For', '127.0.0.1')
            ->getJson('/api/agent/summary')
            ->assertForbidden();
    }

    public function test_requests_fail_closed_when_no_token_is_configured(): void
    {
        config(['services.agent.token' => '']);

        $this->withoutToken()->getJson('/api/agent/summary')->assertStatus(503);
    }

    public function test_auth_runs_before_route_model_binding(): void
    {
        $this->withoutToken()->patchJson('/api/agent/jobs/999', [])->assertUnauthorized();
    }

    public function test_errors_are_json_without_an_accept_header(): void
    {
        $this->post('/api/agent/jobs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url', 'title']);

        $this->patch('/api/agent/jobs/999', [])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    // POST /jobs

    public function test_upsert_creates_a_job_and_a_missing_company(): void
    {
        $this->postJson('/api/agent/jobs', [
            'url' => 'https://example.com/jobs/1',
            'title' => 'Backend Developer',
            'company' => ['name' => 'Acme', 'website' => 'https://acme.test'],
            'platform' => 'linkedin',
            'must_have_skills' => ['PHP'],
            'match_score' => 80,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('platform', 'linkedin')
            ->assertJsonPath('must_have_skills', ['PHP'])
            ->assertJsonPath('company.name', 'Acme');

        $this->assertDatabaseHas('companies', ['name' => 'Acme', 'website' => 'https://acme.test']);
        $this->assertNotNull(Job::sole()->found_at);
    }

    public function test_upsert_reuses_an_existing_company_regardless_of_case(): void
    {
        $company = Company::factory()->create(['name' => 'Acme', 'website' => 'https://keep.test']);

        $this->postJson('/api/agent/jobs', [
            'url' => 'https://example.com/jobs/1',
            'title' => 'Dev',
            'company' => ['name' => 'ACME', 'website' => 'https://other.test'],
        ])->assertCreated()->assertJsonPath('company_id', $company->id);

        $this->assertSame(1, Company::count());
        $this->assertSame('https://keep.test', $company->fresh()->website);
    }

    public function test_upsert_accepts_a_company_id(): void
    {
        $company = Company::factory()->create();

        $this->postJson('/api/agent/jobs', [
            'url' => 'https://example.com/jobs/1',
            'title' => 'Dev',
            'company_id' => $company->id,
        ])->assertCreated()->assertJsonPath('company_id', $company->id);
    }

    public function test_upsert_updates_by_url_and_keeps_fields_it_was_not_given(): void
    {
        $job = Job::factory()->create([
            'url' => 'https://example.com/jobs/1',
            'status' => PipelineStatus::Interview,
            'match_score' => 55,
            'found_at' => '2026-01-01 00:00:00',
        ]);

        $this->postJson('/api/agent/jobs', [
            'url' => 'https://example.com/jobs/1',
            'title' => 'Renamed',
            'company_id' => $job->company_id,
        ])
            ->assertOk()
            ->assertJsonPath('id', $job->id)
            ->assertJsonPath('title', 'Renamed')
            ->assertJsonPath('status', 'interview')
            ->assertJsonPath('match_score', 55);

        $this->assertSame(1, Job::count());
        $this->assertSame('2026-01-01', $job->fresh()->found_at->toDateString());
    }

    public function test_upsert_validates_input(): void
    {
        $this->postJson('/api/agent/jobs', [
            'url' => 'not-a-url',
            'platform' => 'myspace',
            'status' => 'nope',
            'match_score' => 101,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'url', 'title', 'company', 'company_id', 'platform', 'status', 'match_score',
        ]);
    }

    public function test_upsert_rejects_both_company_forms_together(): void
    {
        $company = Company::factory()->create();

        $this->postJson('/api/agent/jobs', [
            'url' => 'https://example.com/jobs/1',
            'title' => 'Dev',
            'company_id' => $company->id,
            'company' => ['name' => 'Other'],
        ])->assertUnprocessable()->assertJsonValidationErrors('company_id');
    }

    // PATCH /jobs/{id}

    public function test_patch_updates_only_the_given_job_fields(): void
    {
        $job = Job::factory()->create(['title' => 'Keep', 'match_score' => 10]);

        $this->patchJson("/api/agent/jobs/{$job->id}", ['match_score' => 90, 'gaps' => ['Go'], 'status' => 'queued'])
            ->assertOk()
            ->assertJsonPath('title', 'Keep')
            ->assertJsonPath('match_score', 90)
            ->assertJsonPath('gaps', ['Go'])
            ->assertJsonPath('status', 'queued');
    }

    public function test_patch_rejects_invalid_data_and_a_url_owned_by_another_job(): void
    {
        $other = Job::factory()->create();
        $job = Job::factory()->create();

        $this->patchJson("/api/agent/jobs/{$job->id}", ['url' => $other->url, 'match_score' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url', 'match_score']);

        $this->patchJson("/api/agent/jobs/{$job->id}", ['url' => $job->url])->assertOk();
    }

    public function test_patch_returns_404_for_a_missing_job(): void
    {
        $this->patchJson('/api/agent/jobs/999', ['title' => 'X'])->assertNotFound();
    }

    // GET /jobs

    public function test_list_filters_jobs_by_status_and_min_score(): void
    {
        $match = Job::factory()->create(['status' => PipelineStatus::Queued, 'match_score' => 80]);
        Job::factory()->create(['status' => PipelineStatus::Queued, 'match_score' => 50]);
        Job::factory()->create(['status' => PipelineStatus::Found, 'match_score' => 90]);
        Job::factory()->create(['status' => PipelineStatus::Queued, 'match_score' => null]);

        $this->getJson('/api/agent/jobs?status=queued&min_score=70')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.company.id', $match->company_id);

        $this->getJson('/api/agent/jobs?min_score=0')->assertJsonCount(3, 'data');
        $this->getJson('/api/agent/jobs?status=&min_score=')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/agent/jobs?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('total', 4);
    }

    public function test_list_validates_filters(): void
    {
        $this->getJson('/api/agent/jobs?status=nope&min_score=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'min_score']);
    }

    // POST /resume-versions

    public function test_resume_version_stores_the_pdf_and_decodes_json_fields(): void
    {
        Storage::fake('resumes');
        $job = Job::factory()->create(['title' => 'Backend Dev']);

        $response = $this->withHeader('Accept', 'application/json')->post('/api/agent/resume-versions', [
            'job_id' => $job->id,
            'tex_source' => '\documentclass{article}',
            'pdf' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            'keywords_covered' => '["Laravel","SQL"]',
            'changes_summary' => '{"added":["Laravel"],"removed":[],"reordered":["Skills"]}',
        ]);

        $response->assertCreated()
            ->assertJsonPath('job_id', $job->id)
            ->assertJsonPath('label', 'Tailored for Backend Dev')
            ->assertJsonPath('is_base', false)
            ->assertJsonPath('keywords_covered', ['Laravel', 'SQL'])
            ->assertJsonPath('changes_summary.reordered', ['Skills'])
            ->assertJsonMissingPath('tex_source');

        Storage::disk('resumes')->assertExists($response->json('pdf_path'));
        $this->assertSame('\documentclass{article}', ResumeVersion::sole()->tex_source);
    }

    public function test_resume_version_accepts_arrays_and_no_pdf(): void
    {
        $job = Job::factory()->create();

        $this->postJson('/api/agent/resume-versions', [
            'job_id' => $job->id,
            'label' => 'v2',
            'tex_source' => 'tex',
            'keywords_covered' => ['Go'],
        ])
            ->assertCreated()
            ->assertJsonPath('label', 'v2')
            ->assertJsonPath('keywords_covered', ['Go'])
            ->assertJsonPath('pdf_path', null);
    }

    public function test_resume_version_rejects_bad_input(): void
    {
        Storage::fake('resumes');

        $this->withHeader('Accept', 'application/json')->post('/api/agent/resume-versions', [
            'job_id' => 999,
            'pdf' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            'keywords_covered' => '{not json',
            'changes_summary' => '{"unexpected":[]}',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id', 'tex_source', 'pdf', 'keywords_covered', 'changes_summary']);

        $this->assertSame([], Storage::disk('resumes')->allFiles());
        $this->assertSame(0, ResumeVersion::count());
    }

    // POST /applications

    public function test_application_creates_an_initial_event_and_moves_the_job(): void
    {
        $job = Job::factory()->create();
        $resume = ResumeVersion::factory()->create(['job_id' => $job->id]);

        $this->postJson('/api/agent/applications', [
            'job_id' => $job->id,
            'resume_version_id' => $resume->id,
            'applied_via' => 'Easy Apply',
            'screening_answers' => [['question' => 'Notice?', 'answer' => '30 days']],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'applied')
            ->assertJsonPath('screening_answers.0.answer', '30 days')
            ->assertJsonPath('status_events.0.from_status', null)
            ->assertJsonPath('status_events.0.to_status', 'applied')
            ->assertJsonPath('status_events.0.source', 'claude');

        $this->assertNotNull(Application::sole()->applied_at);
        $this->assertSame(PipelineStatus::Applied, $job->fresh()->status);
    }

    public function test_duplicate_application_for_a_job_is_rejected(): void
    {
        $existing = Application::factory()->create();

        $this->postJson('/api/agent/applications', ['job_id' => $existing->job_id])
            ->assertConflict()
            ->assertJsonPath('application_id', $existing->id);

        $this->assertSame(1, Application::count());
        $this->assertSame(0, $existing->statusEvents()->count());
    }

    public function test_application_only_accepts_this_jobs_resume_or_the_base_resume(): void
    {
        $job = Job::factory()->create();
        $otherJobResume = ResumeVersion::factory()->create();

        $this->postJson('/api/agent/applications', ['job_id' => $job->id, 'resume_version_id' => $otherJobResume->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resume_version_id');

        $base = ResumeVersion::factory()->base()->create();

        $this->postJson('/api/agent/applications', ['job_id' => $job->id, 'resume_version_id' => $base->id])
            ->assertCreated();
    }

    public function test_application_validates_input(): void
    {
        $this->postJson('/api/agent/applications', ['job_id' => 999, 'status' => 'nope', 'applied_at' => 'soon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id', 'status', 'applied_at']);
    }

    public function test_dates_with_an_offset_are_stored_in_the_app_timezone(): void
    {
        $job = Job::factory()->create();

        $this->postJson('/api/agent/applications', ['job_id' => $job->id, 'applied_at' => '2026-11-03T10:00:00+05:30'])
            ->assertCreated()
            ->assertJsonPath('applied_at', '2026-11-03T04:30:00.000000Z');
    }

    // POST /applications/{id}/status

    public function test_status_change_records_an_event_and_updates_application_and_job(): void
    {
        $application = Application::factory()->create(['next_step' => 'keep me']);
        $email = Email::factory()->create();

        $this->postJson("/api/agent/applications/{$application->id}/status", [
            'to_status' => 'interview',
            'source' => 'gmail',
            'evidence' => 'Invite received',
            'email_id' => $email->id,
            'happened_at' => '2026-11-03T10:00:00+05:30',
        ])
            ->assertCreated()
            ->assertJsonPath('from_status', 'applied')
            ->assertJsonPath('to_status', 'interview')
            ->assertJsonPath('source', 'gmail')
            ->assertJsonPath('email_id', $email->id)
            ->assertJsonPath('happened_at', '2026-11-03T04:30:00.000000Z')
            ->assertJsonPath('application.status', 'interview')
            ->assertJsonPath('application.next_step', 'keep me');

        $this->assertSame(PipelineStatus::Interview, $application->job->fresh()->status);
    }

    public function test_status_change_defaults_source_and_can_set_the_next_step(): void
    {
        $application = Application::factory()->create();

        $this->postJson("/api/agent/applications/{$application->id}/status", [
            'to_status' => 'online_test',
            'next_step' => 'HackerRank test',
            'next_step_at' => '2026-11-05T09:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('source', 'claude')
            ->assertJsonPath('application.next_step', 'HackerRank test');
    }

    public function test_status_change_rejects_bad_input_and_records_nothing(): void
    {
        $application = Application::factory()->create();

        $this->postJson("/api/agent/applications/{$application->id}/status", ['to_status' => 'applied'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_status');

        $this->postJson("/api/agent/applications/{$application->id}/status", [
            'to_status' => 'nope',
            'source' => 'fax',
            'email_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['to_status', 'source', 'email_id']);

        $this->postJson('/api/agent/applications/999/status', ['to_status' => 'offer'])->assertNotFound();

        $this->assertSame(0, $application->statusEvents()->count());
    }

    // POST /companies/{id}/rounds

    public function test_round_is_created_for_the_company(): void
    {
        $company = Company::factory()->create();

        $this->postJson("/api/agent/companies/{$company->id}/rounds", [
            'round_name' => 'Online assessment',
            'round_type' => 'aptitude',
            'typical_duration' => '60 min',
        ])
            ->assertCreated()
            ->assertJsonPath('company_id', $company->id)
            ->assertJsonPath('round_type', 'aptitude');
    }

    public function test_round_validates_input_and_company(): void
    {
        $company = Company::factory()->create();

        $this->postJson("/api/agent/companies/{$company->id}/rounds", ['round_type' => 'karaoke'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['round_name', 'round_type']);

        $this->postJson('/api/agent/companies/999/rounds', ['round_name' => 'X', 'round_type' => 'hr'])
            ->assertNotFound();
    }

    // POST /companies/{id}/questions

    public function test_questions_are_created_in_bulk(): void
    {
        $company = Company::factory()->create();

        $this->postJson("/api/agent/companies/{$company->id}/questions", ['questions' => [
            ['question' => 'What is a JOIN?', 'round_type' => 'technical', 'topic' => 'SQL', 'year_reported' => 2026, 'answer_based_on' => ['projects' => ['JobHunt']]],
            ['question' => 'Tell me about yourself'],
        ]])
            ->assertCreated()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('questions.0.answer_based_on.projects', ['JobHunt']);

        $this->assertSame(2, $company->interviewQuestions()->count());
    }

    public function test_questions_already_saved_or_repeated_in_a_batch_are_skipped(): void
    {
        $company = Company::factory()->create();
        InterviewQuestion::factory()->for($company)->create(['question' => 'What is a JOIN?']);

        $this->postJson("/api/agent/companies/{$company->id}/questions", ['questions' => [
            ['question' => '  what is a   join? '],
            ['question' => 'Explain indexing.'],
            ['question' => 'explain INDEXING.'],
        ]])
            ->assertCreated()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('skipped', 2);

        $this->assertSame(2, $company->interviewQuestions()->count());
    }

    public function test_a_round_with_the_same_name_is_not_duplicated(): void
    {
        $company = Company::factory()->create();

        $first = $this->postJson("/api/agent/companies/{$company->id}/rounds", ['round_name' => 'Online test', 'round_type' => 'aptitude'])
            ->assertCreated();
        $this->postJson("/api/agent/companies/{$company->id}/rounds", ['round_name' => 'ONLINE TEST', 'round_type' => 'coding'])
            ->assertOk()
            ->assertJsonPath('id', $first->json('id'))
            ->assertJsonPath('round_type', 'aptitude');

        $this->assertSame(1, $company->interviewRounds()->count());
    }

    public function test_questions_are_all_rejected_if_one_is_invalid(): void
    {
        $company = Company::factory()->create();

        $this->postJson("/api/agent/companies/{$company->id}/questions", ['questions' => [
            ['question' => 'Valid?'],
            ['topic' => 'No question text'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('questions.1.question');

        $this->postJson("/api/agent/companies/{$company->id}/questions", ['questions' => []])
            ->assertUnprocessable();

        $this->postJson('/api/agent/companies/999/questions', ['questions' => [['question' => 'Q?']]])
            ->assertNotFound();

        $this->assertSame(0, InterviewQuestion::count());
    }

    public function test_questions_ignore_fields_owned_by_the_user(): void
    {
        $company = Company::factory()->create();

        $this->postJson("/api/agent/companies/{$company->id}/questions", ['questions' => [
            ['question' => 'Q?', 'practiced' => true, 'my_notes' => 'overwritten'],
        ]])->assertCreated();

        $question = InterviewQuestion::sole();
        $this->assertFalse($question->fresh()->practiced);
        $this->assertNull($question->my_notes);
    }

    // GET /jobs/{id}

    public function test_show_job_includes_resume_versions_with_pdf_path_and_the_application(): void
    {
        $job = Job::factory()->create(['jd_text' => 'Full JD']);
        $resume = ResumeVersion::factory()->for($job)->create(['pdf_path' => 'abc.pdf']);
        $application = Application::factory()->for($job)->create(['resume_version_id' => $resume->id]);

        $this->getJson("/api/agent/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('jd_text', 'Full JD')
            ->assertJsonPath('company.id', $job->company_id)
            ->assertJsonPath('resume_versions.0.id', $resume->id)
            ->assertJsonPath('resume_versions.0.pdf_file', Storage::disk('resumes')->path('abc.pdf'))
            ->assertJsonMissingPath('resume_versions.0.tex_source')
            ->assertJsonPath('application.id', $application->id);

        $this->getJson('/api/agent/jobs/999')->assertNotFound();
    }

    // GET /applications

    public function test_applications_are_listed_with_job_company_and_stage_reached(): void
    {
        $application = Application::factory()->create();
        $application->transitionTo(PipelineStatus::Interview, \App\Enums\StatusEventSource::Manual);
        $application->transitionTo(PipelineStatus::Rejected, \App\Enums\StatusEventSource::Manual);
        Application::factory()->create();

        $this->getJson('/api/agent/applications?status=rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $application->id)
            ->assertJsonPath('data.0.job.company.id', $application->job->company_id)
            ->assertJsonPath('data.0.stage_reached', 'interview');

        $this->getJson('/api/agent/applications')->assertJsonCount(2, 'data');
        $this->getJson('/api/agent/applications?status=nope')->assertUnprocessable();
    }

    // GET and POST /companies

    public function test_companies_can_be_searched_and_found_or_created_by_name(): void
    {
        $acme = Company::factory()->create(['name' => 'Acme Labs', 'website' => 'https://keep.test']);
        InterviewRound::factory()->for($acme)->create(['round_name' => 'HR']);
        Company::factory()->create(['name' => 'Other']);

        $this->getJson('/api/agent/companies?search=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.interview_rounds.0.round_name', 'HR');

        $this->postJson('/api/agent/companies', ['name' => 'ACME LABS', 'website' => 'https://other.test'])
            ->assertOk()
            ->assertJsonPath('id', $acme->id)
            ->assertJsonPath('website', 'https://keep.test');

        $this->postJson('/api/agent/companies', ['name' => 'Zenith', 'website' => 'https://zenith.test'])
            ->assertCreated()
            ->assertJsonPath('name', 'Zenith');

        $this->postJson('/api/agent/companies', ['website' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'website']);
    }

    // GET /emails

    public function test_emails_are_filtered_by_processed(): void
    {
        $unprocessed = Email::factory()->create(['processed' => false]);
        $processed = Email::factory()->create(['processed' => true]);

        $this->getJson('/api/agent/emails?processed=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unprocessed->id);

        $this->getJson('/api/agent/emails?processed=1')->assertJsonPath('data.0.id', $processed->id);
        $this->getJson('/api/agent/emails')->assertJsonCount(2, 'data');
        $this->getJson('/api/agent/emails?processed=maybe')->assertUnprocessable();
    }

    // PATCH /emails/{id}

    public function test_email_can_be_matched_classified_and_processed(): void
    {
        $email = Email::factory()->create();
        $application = Application::factory()->create();

        $this->patchJson("/api/agent/emails/{$email->id}", [
            'matched_application_id' => $application->id,
            'classification' => 'interview_invite',
            'processed' => true,
            'subject' => 'ignored',
        ])
            ->assertOk()
            ->assertJsonPath('matched_application_id', $application->id)
            ->assertJsonPath('classification', 'interview_invite')
            ->assertJsonPath('processed', true)
            ->assertJsonPath('subject', $email->subject);
    }

    public function test_email_patch_validates_input(): void
    {
        $email = Email::factory()->create();

        $this->patchJson("/api/agent/emails/{$email->id}", ['matched_application_id' => 999, 'processed' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['matched_application_id', 'processed']);

        $this->patchJson('/api/agent/emails/999', ['processed' => true])->assertNotFound();
    }

    // GET /summary

    public function test_summary_counts_by_status_and_lists_this_weeks_applications(): void
    {
        $this->travelTo('2026-11-04 12:00:00'); // a Wednesday

        Job::factory()->count(2)->create(['status' => PipelineStatus::Found]);

        $company = Company::factory()->create(['name' => 'Acme']);
        $job = Job::factory()->for($company)->create(['title' => 'Dev', 'status' => PipelineStatus::Applied]);
        // The week starts at Monday 00:00 IST, which is 18:30 UTC on the Sunday.
        $monday = Application::factory()->for($job)->create(['applied_at' => '2026-11-01 18:30:00']);
        Application::factory()->create(['applied_at' => '2026-11-01 18:29:59']);
        Application::factory()->create(['applied_at' => '2026-11-08 18:30:00']);

        $this->getJson('/api/agent/summary')
            ->assertOk()
            ->assertJsonPath('jobs_by_status.found', 4)
            ->assertJsonPath('jobs_by_status.applied', 1)
            ->assertJsonPath('jobs_by_status.offer', 0)
            ->assertJsonPath('applications_by_status.applied', 3)
            ->assertJsonPath('applications_by_status.rejected', 0)
            ->assertJsonPath('this_week.from', '2026-11-01T18:30:00.000000Z')
            ->assertJsonPath('this_week.count', 1)
            ->assertJsonPath('this_week.applications.0.id', $monday->id)
            ->assertJsonPath('this_week.applications.0.title', 'Dev')
            ->assertJsonPath('this_week.applications.0.company', 'Acme')
            ->assertJsonPath('this_week.applications.0.status', 'applied');
    }

    public function test_summary_is_zeroed_on_an_empty_database(): void
    {
        $this->getJson('/api/agent/summary')
            ->assertOk()
            ->assertJsonPath('jobs_by_status.found', 0)
            ->assertJsonPath('this_week.count', 0)
            ->assertJsonPath('this_week.applications', []);
    }
}
