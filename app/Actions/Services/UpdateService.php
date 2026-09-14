<?php

namespace App\Actions\Services;

use App\Data\ServiceData;
use App\Models\Service;

final class UpdateService
{
    public function handle(Service $service, ServiceData $data): Service
    {
        $service->update([
            'name' => $data->name,
            'code' => $data->code,
            'description' => $data->description,
            'is_active' => $data->isActive,
        ]);

        return $service->refresh();
    }
}
