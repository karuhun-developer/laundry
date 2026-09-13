<?php

namespace App\Actions\Cities;

use App\Data\CityData;
use App\Models\City;

final class CreateCity
{
    public function handle(CityData $data): City
    {
        return City::query()->create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'province' => $data->province,
        ]);
    }
}
