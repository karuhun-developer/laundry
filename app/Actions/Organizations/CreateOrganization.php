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
            'is_active' => $data->isActive,
            'qris_enabled' => $data->qrisEnabled,
            'qris_api_key' => $data->qrisApiKey,
            'qris_base_url' => $data->qrisBaseUrl,
        ]);
    }
}
