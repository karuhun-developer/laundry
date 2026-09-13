<?php

namespace App\Actions\Cities;

use App\Data\CityData;
use App\Models\City;

final class UpdateCity
{
    public function handle(City $city, CityData $data): City
    {
        $city->update([
            'name' => $data->name,
            'province' => $data->province,
        ]);

        return $city->refresh();
    }
}
