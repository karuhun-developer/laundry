<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Cities\CreateCity;
use App\Data\CityData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCityRequest;
use Illuminate\Http\RedirectResponse;

class CityController extends Controller
{
    public function store(StoreCityRequest $request, CreateCity $createCity): RedirectResponse
    {
        $createCity->handle(CityData::fromRequest($request));

        return back()->with('success', 'Kota berhasil ditambahkan.');
    }
}
