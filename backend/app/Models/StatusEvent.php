<?php

namespace App\Models;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id', 'from_status', 'to_status', 'source', 'evidence', 'email_id', 'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PipelineStatus::class,
            'to_status' => PipelineStatus::class,
            'source' => StatusEventSource::class,
            'happened_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
