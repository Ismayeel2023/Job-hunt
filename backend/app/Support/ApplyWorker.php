<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Whether a Claude Code session is running /apply-worker, judged by when it last checked in.
 */
class ApplyWorker
{
    public static function checkIn(): void
    {
        Setting::putValue('apply_worker_seen_at', now()->toIso8601String());
    }

    public static function status(): array
    {
        $seen = Setting::getValue('apply_worker_seen_at');
        $seenAt = $seen ? Carbon::parse($seen) : null;

        return [
            'online' => $seenAt !== null && $seenAt->isAfter(now()->subSeconds(90)),
            'seen_at' => $seenAt,
        ];
    }
}
