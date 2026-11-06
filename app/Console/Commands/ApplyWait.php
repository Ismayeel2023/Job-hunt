<?php

namespace App\Console\Commands;

use App\Models\ApplyRun;
use App\Support\ApplyWorker;
use Illuminate\Console\Command;

/**
 * Used by /apply-worker while the job page waits on the user: returns as soon as they answer, approve or cancel.
 */
class ApplyWait extends Command
{
    protected $signature = 'jobhunt:apply-wait {run : The apply run id} {--wait=540 : Seconds to wait before giving up}';

    protected $description = 'Wait for the user to answer, approve or cancel an apply run (for the Claude worker)';

    public function handle(): int
    {
        $deadline = microtime(true) + max(0, (int) $this->option('wait'));

        do {
            ApplyWorker::checkIn();
            $run = ApplyRun::find($this->argument('run'));

            if (! $run) {
                $this->line(json_encode(['error' => 'No such run']));

                return self::FAILURE;
            }

            $answered = $run->status->value === 'running' && $run->questions && $run->allAnswered();
            $decided = $run->confirmed_at || $run->cancelled_at || ! $run->status->isActive();

            if ($answered || $decided) {
                break;
            }

            if (microtime(true) < $deadline) {
                usleep(2_000_000);
            }
        } while (microtime(true) < $deadline);

        $this->line(json_encode([
            'run_id' => $run->id,
            'status' => $run->status,
            'approved' => $run->confirmed_at !== null && $run->status->isActive(),
            'cancelled' => $run->cancelled_at !== null,
            'questions' => $run->questions ?? [],
            'still_waiting' => ! ($answered || $decided),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
