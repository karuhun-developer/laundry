<?php

namespace App\Actions\Cashflow;

use App\Data\CashflowCategoryData;
use App\Models\CashflowCategory;

final readonly class CreateCashflowCategory
{
    public function handle(CashflowCategoryData $data): CashflowCategory
    {
        return CashflowCategory::query()->create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'type' => $data->type,
            'group' => $data->group,
            'is_system' => false,
            'is_active' => true,
        ]);
    }
}
