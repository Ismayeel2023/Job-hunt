<?php

namespace App\Enums;

enum ApplyRunStatus: string
{
    case Requested = 'requested';
    case Running = 'running';
    case NeedsAnswer = 'needs_answer';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Submitting = 'submitting';
    case Submitted = 'submitted';
    case Failed = 'failed';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Requested, self::Running, self::NeedsAnswer, self::AwaitingConfirmation, self::Submitting], true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isActive()));
    }
}
