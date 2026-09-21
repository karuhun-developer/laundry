<?php

namespace App\Enums;

enum CashflowType: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Pemasukan',
            self::Credit => 'Pengeluaran',
        };
    }
}
