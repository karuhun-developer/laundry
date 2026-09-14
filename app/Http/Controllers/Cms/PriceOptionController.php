<?php

namespace App\Http\Controllers\Cms;

use App\Actions\PriceOptions\CreatePriceOption;
use App\Actions\PriceOptions\DeletePriceOption;
use App\Actions\PriceOptions\UpdatePriceOption;
use App\Data\PriceOptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeletePriceOptionRequest;
use App\Http\Requests\Cms\StorePriceOptionRequest;
use App\Http\Requests\Cms\UpdatePriceOptionRequest;
use App\Models\PriceOption;
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

    public function update(
        UpdatePriceOptionRequest $request,
        PriceOption $priceOption,
        UpdatePriceOption $updatePriceOption,
    ): RedirectResponse {
        $updatePriceOption->handle($priceOption, PriceOptionData::fromRequest($request));

        return back()->with('success', 'Paket layanan berhasil diperbarui.');
    }

    public function destroy(
        DeletePriceOptionRequest $request,
        PriceOption $priceOption,
        DeletePriceOption $deletePriceOption,
    ): RedirectResponse {
        $deletePriceOption->handle($priceOption);

        return back()->with('success', 'Paket layanan berhasil dihapus.');
    }
}
