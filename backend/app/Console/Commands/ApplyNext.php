<?php

namespace App\Console\Commands;

use App\Enums\ApplyRunStatus;
use App\Models\ApplyRun;
use App\Models\ResumeVersion;
use App\Support\ApplyWorker;
use App\Support\CandidateDetails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Used by /apply-worker: waits for someone to click Apply, claims the run, and prints what Claude needs as JSON.
 */
class ApplyNext extends Command
{
    protected $signature = 'jobhunt:apply-next {--wait=540 : Seconds to wait for a request before giving up}';

    protected $description = 'Claim the next Apply request from the job page (for the Claude worker)';

    public function handle(): int
    {
        $deadline = microtime(true) + max(0, (int) $this->option('wait'));

        do {
            ApplyWorker::checkIn();
            $run = $this->claim();

            if ($run) {
                $this->line(json_encode($this->payload($run), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            if (microtime(true) < $deadline) {
                usleep(2_000_000);
            }
        } while (microtime(true) < $deadline);

        $this->line(json_encode(['run_id' => null]));

        return self::SUCCESS;
    }

    private function claim(): ?ApplyRun
    {
        foreach (ApplyRun::where('status', ApplyRunStatus::Requested)->orderBy('id')->pluck('id') as $id) {
            $claimed = ApplyRun::where('id', $id)->where('status', ApplyRunStatus::Requested)
                ->update(['status' => ApplyRunStatus::Running, 'claimed_at' => now()]);

            if ($claimed) {
                return ApplyRun::with('job.company')->find($id);
            }
        }

        return null;
    }

    private function payload(ApplyRun $run): array
    {
        $resume = ResumeVersion::where('job_id', $run->job_id)->latest('id')->first();

        return [
            'run_id' => $run->id,
            'job' => [
                'id' => $run->job->id,
                'title' => $run->job->title,
                'company' => $run->job->company->name,
                'url' => $run->job->url,
                'platform' => $run->job->platform,
            ],
            'resume' => $resume ? [
                'id' => $resume->id,
                'label' => $resume->label,
                'pdf_file' => $resume->pdf_path ? Storage::disk('resumes')->path($resume->pdf_path) : null,
            ] : null,
            'candidate' => CandidateDetails::get(),
            'saved_answers' => CandidateDetails::savedAnswers(),
        ];
    }
}
