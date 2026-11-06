<?php

namespace App\Models;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id', 'resume_version_id', 'applied_at', 'applied_via', 'cover_note', 'screening_answers',
        'status', 'next_step', 'next_step_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'screening_answers' => 'array',
            'status' => PipelineStatus::class,
            'next_step_at' => 'datetime',
        ];
    }

    /**
     * Record a status event, then move this application and its job to the new status.
     *
     * @param  array{evidence?: ?string, email_id?: ?int, happened_at?: mixed, next_step?: ?string, next_step_at?: mixed}  $details
     */
    public function transitionTo(PipelineStatus $status, StatusEventSource $source, array $details = []): StatusEvent
    {
        return DB::transaction(function () use ($status, $source, $details) {
            $event = $this->statusEvents()->create([
                'from_status' => $this->status,
                'to_status' => $status,
                'source' => $source,
                'evidence' => $details['evidence'] ?? null,
                'email_id' => $details['email_id'] ?? null,
                'happened_at' => $details['happened_at'] ?? now(),
            ]);

            $this->update([
                ...Arr::only($details, ['next_step', 'next_step_at']),
                'status' => $status,
            ]);

            $this->job->update(['status' => $status]);

            return $event;
        });
    }

    /**
     * The furthest stage this application got to, even if it has since closed.
     */
    public function stageReached(): ?PipelineStatus
    {
        return $this->statusEvents->flatMap(fn (StatusEvent $event) => [$event->from_status, $event->to_status])
            ->push($this->status)
            ->filter(fn (?PipelineStatus $status) => $status?->stageRank() > 0)
            ->sortBy(fn (PipelineStatus $status) => $status->stageRank())
            ->last();
    }

    /**
     * Whether the company has replied at all, including with a rejection.
     */
    public function hasResponse(): bool
    {
        return $this->statusEvents->pluck('to_status')
            ->push($this->status)
            ->contains(fn (PipelineStatus $status) => in_array($status, [
                PipelineStatus::OnlineTest, PipelineStatus::Interview, PipelineStatus::Hr, PipelineStatus::Offer, PipelineStatus::Rejected,
            ], true));
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEvent::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class, 'matched_application_id');
    }
}
