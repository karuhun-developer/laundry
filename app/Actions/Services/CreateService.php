<?php

namespace App\Actions\Services;

use App\Data\ServiceData;
use App\Models\Service;

final class CreateService
{
    public function handle(ServiceData $data): Service
    {
        return Service::query()->create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'code' => $data->code,
            'description' => $data->description,
            'is_active' => $data->isActive,
        ]);
    }
}
