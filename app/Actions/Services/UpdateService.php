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
            'completion_duration_hours' => $data->completionDurationHours ?? $service->completion_duration_hours,
        ]);

        return $service->refresh();
    }
}
