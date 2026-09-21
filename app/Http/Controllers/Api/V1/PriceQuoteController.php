<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\QuotePriceOption;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PriceQuoteRequest;
use App\Http\Resources\PriceQuoteResource;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PriceOption;

class PriceQuoteController extends Controller
{
    public function __invoke(
        PriceQuoteRequest $request,
        Organization $organization,
        Branch $branch,
        PriceOption $priceOption,
        QuotePriceOption $quotePriceOption,
    ): PriceQuoteResource {
        return new PriceQuoteResource($quotePriceOption->handle(
            organization: $organization,
            branch: $branch,
            priceOption: $priceOption,
            weightGrams: $request->integer('weight_grams'),
            customPricePerKilogramRupiah: $request->filled('custom_price_per_kilogram_rupiah')
                ? $request->integer('custom_price_per_kilogram_rupiah')
                : null,
        ));
    }
}
