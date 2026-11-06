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
use App\Models\Setting;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_factory_persists_a_row(): void
    {
        foreach ([Company::class, Job::class, ResumeVersion::class, Application::class, StatusEvent::class,
            InterviewRound::class, InterviewQuestion::class, Email::class, Setting::class] as $model) {
            $this->assertModelExists($model::factory()->create());
        }
    }

    public function test_job_url_is_unique(): void
    {
        Job::factory()->create(['url' => 'https://example.com/1']);

        $this->expectException(QueryException::class);
        Job::factory()->create(['url' => 'https://example.com/1']);
    }

    public function test_gmail_message_id_is_unique(): void
    {
        Email::factory()->create(['gmail_message_id' => 'abc']);

        $this->expectException(QueryException::class);
        Email::factory()->create(['gmail_message_id' => 'abc']);
    }

    public function test_match_score_must_be_0_to_100(): void
    {
        $this->assertSame(100, Job::factory()->create(['match_score' => 100])->fresh()->match_score);

        $this->expectException(InvalidArgumentException::class);
        Job::factory()->make(['match_score' => 101]);
    }

    public function test_casts_round_trip(): void
    {
        $job = Job::factory()->create(['status' => PipelineStatus::OnlineTest, 'gaps' => ['Go']])->fresh();

        $this->assertSame(PipelineStatus::OnlineTest, $job->status);
        $this->assertSame(['Go'], $job->gaps);
    }

    public function test_deleting_a_company_cascades_but_keeps_the_resume_version(): void
    {
        $job = Job::factory()->create();
        $resume = ResumeVersion::factory()->create(['job_id' => $job->id]);
        $application = Application::factory()->create(['job_id' => $job->id, 'resume_version_id' => $resume->id]);
        $event = StatusEvent::factory()->create(['application_id' => $application->id]);
        $email = Email::factory()->create(['matched_application_id' => $application->id]);

        $job->company->delete();

        $this->assertModelMissing($job);
        $this->assertModelMissing($application);
        $this->assertModelMissing($event);
        $this->assertNull($resume->fresh()->job_id);
        $this->assertNull($email->fresh()->matched_application_id);
    }

    public function test_seeder_creates_one_user_and_base_resume_and_preserves_edits(): void
    {
        $this->seed(DatabaseSeeder::class);

        $base = ResumeVersion::where('is_base', true)->sole();
        $this->assertNull($base->job_id);
        $this->assertStringContainsString('PLACEHOLDER', $base->tex_source);

        ResumeVersion::where('id', $base->id)->update(['tex_source' => 'real resume']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame('real resume', ResumeVersion::where('is_base', true)->sole()->tex_source);
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
