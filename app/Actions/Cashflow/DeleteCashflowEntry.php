<?php

namespace App\Actions\Cashflow;

use App\Models\CashflowEntry;

final readonly class DeleteCashflowEntry
{
    public function handle(CashflowEntry $entry): void
    {
        $entry->delete();
    }
}
