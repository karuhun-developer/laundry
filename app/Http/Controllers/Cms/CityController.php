<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Cities\CreateCity;
use App\Actions\Cities\DeleteCity;
use App\Actions\Cities\UpdateCity;
use App\Data\CityData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteCityRequest;
use App\Http\Requests\Cms\StoreCityRequest;
use App\Http\Requests\Cms\UpdateCityRequest;
use App\Models\City;
use Illuminate\Http\RedirectResponse;

class CityController extends Controller
{
    public function store(StoreCityRequest $request, CreateCity $createCity): RedirectResponse
    {
        $createCity->handle(CityData::fromRequest($request));

        return back()->with('success', 'Kota berhasil ditambahkan.');
    }

    public function update(
        UpdateCityRequest $request,
        City $city,
        UpdateCity $updateCity,
    ): RedirectResponse {
        $updateCity->handle($city, CityData::fromRequest($request));

        return back()->with('success', 'Kota berhasil diperbarui.');
    }

    public function destroy(
        DeleteCityRequest $request,
        City $city,
        DeleteCity $deleteCity,
    ): RedirectResponse {
        $deleteCity->handle($city);

        return back()->with('success', 'Kota berhasil dihapus.');
    }
}
