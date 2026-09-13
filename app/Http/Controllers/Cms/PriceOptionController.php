<?php

namespace App\Http\Controllers\Cms;

use App\Actions\PriceOptions\CreatePriceOption;
use App\Data\PriceOptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePriceOptionRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;

class PriceOptionController extends Controller
{
    public function store(
        StorePriceOptionRequest $request,
        Service $service,
        CreatePriceOption $createPriceOption,
    ): RedirectResponse {
        $createPriceOption->handle($service, PriceOptionData::fromRequest($request));

        return back()->with('success', 'Tarif layanan berhasil dibuat.');
    }
}
