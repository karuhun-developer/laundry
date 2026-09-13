<?php

namespace App\Actions\Branches;

use App\Data\BranchData;
use App\Models\Branch;

final class CreateBranch
{
    public function handle(BranchData $data): Branch
    {
        return Branch::query()->create([
            'organization_id' => $data->organizationId,
            'city_id' => $data->cityId,
            'name' => $data->name,
            'code' => $data->code,
            'phone' => $data->phone,
            'address' => $data->address,
            'is_active' => $data->isActive,
        ]);
    }
}
