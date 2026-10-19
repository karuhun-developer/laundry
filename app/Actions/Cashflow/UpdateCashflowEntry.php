<?php

namespace App\Actions\Cashflow;

use App\Data\CashflowEntryData;
use App\Models\CashflowEntry;

final readonly class UpdateCashflowEntry
{
    public function handle(CashflowEntry $entry, CashflowEntryData $data): CashflowEntry
    {
        $entry->update([
            'organization_id' => $data->organizationId,
            'branch_id' => $data->branchId,
            'cashflow_category_id' => $data->cashflowCategoryId,
            'type' => $data->type,
            'amount_rupiah' => $data->amountRupiah,
            'description' => $data->description,
            'entry_date' => $data->entryDate,
        ]);

        return $entry->refresh();
    }
}
