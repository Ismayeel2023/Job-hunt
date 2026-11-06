<?php

namespace App\Models;

use App\Enums\ApplyRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One request to apply to a job, handed from the Apply button to the Claude worker, with its live progress.
 */
class ApplyRun extends Model
{
    /** The steps the progress view shows, in order. */
    public const STEPS = [
        'check_rules' => 'Checking it is an apply day',
        'open_posting' => 'Opening the posting',
        'fill_form' => 'Filling in the form',
        'upload_resume' => 'Uploading your resume',
        'screening' => 'Answering screening questions',
        'review' => 'Waiting for your OK',
        'submit' => 'Submitting',
        'record' => 'Saving to JobHunt',
    ];

    protected $fillable = [
        'job_id', 'status', 'step', 'log', 'questions', 'summary', 'error', 'application_id',
        'claimed_at', 'confirmed_at', 'cancelled_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplyRunStatus::class,
            'log' => 'array',
            'questions' => 'array',
            'summary' => 'array',
            'claimed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ApplyRunStatus::active());
    }

    /**
     * Records progress: moves to the step and adds a line to the log.
     */
    public function addNote(?string $step, ?string $message): void
    {
        if ($step) {
            $this->step = $step;
        }

        if ($step || $message) {
            $this->log = [...($this->log ?? []), ['step' => $step ?? $this->step, 'message' => $message, 'at' => now()->toIso8601String()]];
        }
    }

    /**
     * @param  list<string>  $questions
     */
    public function ask(array $questions): void
    {
        $this->questions = [
            ...($this->questions ?? []),
            ...array_map(fn (string $question) => ['id' => (string) Str::ulid(), 'question' => $question, 'answer' => null], $questions),
        ];
    }

    public function allAnswered(): bool
    {
        return collect($this->questions ?? [])->every(fn (array $question) => filled($question['answer']));
    }
}
