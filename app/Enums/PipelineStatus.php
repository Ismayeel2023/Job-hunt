<?php

namespace App\Enums;

enum PipelineStatus: string
{
    case Found = 'found';
    case Queued = 'queued';
    case Applied = 'applied';
    case OnlineTest = 'online_test';
    case Interview = 'interview';
    case Hr = 'hr';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Ghosted = 'ghosted';

    /**
     * The stages an application moves through, in order.
     *
     * @return list<self>
     */
    public static function stages(): array
    {
        return [self::Applied, self::OnlineTest, self::Interview, self::Hr, self::Offer];
    }

    /**
     * Position in stages(), starting at 1, or 0 for a status that is not a stage.
     */
    public function stageRank(): int
    {
        return (int) array_search($this, self::stages(), true) + (in_array($this, self::stages(), true) ? 1 : 0);
    }
}
