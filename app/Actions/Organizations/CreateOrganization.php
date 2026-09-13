<?php

namespace App\Actions\Organizations;

use App\Data\OrganizationData;
use App\Models\Organization;

final class CreateOrganization
{
    public function handle(OrganizationData $data): Organization
    {
        return Organization::query()->create([
            'name' => $data->name,
            'slug' => $data->slug,
            'is_active' => $data->isActive,
        ]);
    }
}
