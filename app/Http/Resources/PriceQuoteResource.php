<?php

namespace App\Http\Resources;

use App\Data\PriceQuoteData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PriceQuoteData $quote */
        $quote = $this->resource;

        return [
            'price_option' => new PriceOptionResource($quote->priceOption),
            'requested_weight_grams' => $quote->requestedWeightGrams,
            'chargeable_weight_grams' => $quote->chargeableWeightGrams,
            'base_price_rupiah' => $quote->basePriceRupiah,
            'excess_weight_grams' => $quote->excessWeightGrams,
            'excess_price_rupiah' => $quote->excessPriceRupiah,
            'total_rupiah' => $quote->totalRupiah,
        ];
    }
}
