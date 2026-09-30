<?php

namespace App\Enums;

enum OrderTaskStatus: string
{
    case Waiting = 'waiting';
    case Scheduled = 'scheduled';
    case EnRoute = 'en_route';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
