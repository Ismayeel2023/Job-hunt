<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'gmail_account', 'gmail_message_id', 'thread_id', 'from', 'subject', 'snippet', 'received_at',
        'matched_application_id', 'classification', 'processed',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed' => 'boolean',
        ];
    }

    public function matchedApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'matched_application_id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEvent::class);
    }
}
