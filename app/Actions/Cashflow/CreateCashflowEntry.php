<?php

namespace App\Actions\Cashflow;

use App\Data\CashflowEntryData;
use App\Models\CashflowEntry;
use App\Models\User;

final readonly class CreateCashflowEntry
{
    public function handle(CashflowEntryData $data, User $actor): CashflowEntry
    {
        return CashflowEntry::create([
            'organization_id' => $data->organizationId,
            'branch_id' => $data->branchId,
            'cashflow_category_id' => $data->cashflowCategoryId,
            'created_by_id' => $actor->id,
            'type' => $data->type,
            'amount_rupiah' => $data->amountRupiah,
            'description' => $data->description,
            'entry_date' => $data->entryDate,
        ]);
    }
}
