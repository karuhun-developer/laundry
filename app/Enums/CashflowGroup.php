<?php

namespace App\Enums;

enum CashflowGroup: string
{
    case Rutin = 'rutin';
    case NonRutin = 'non_rutin';

    public function label(): string
    {
        return match ($this) {
            self::Rutin => 'Rutin',
            self::NonRutin => 'Non-Rutin',
        };
    }
}
