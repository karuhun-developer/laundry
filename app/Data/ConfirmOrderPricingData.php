<?php

namespace App\Data;

use App\Http\Requests\Api\V1\StoreOrderPricingRequest;

final readonly class ConfirmOrderPricingData
{
    public function __construct(
        public int $priceOptionId,
        public int $weightGrams,
        public ?int $customPricePerKilogramRupiah = null,
    ) {}

    public static function fromRequest(StoreOrderPricingRequest $request): self
    {
        return new self(
            priceOptionId: $request->integer('price_option_id'),
            weightGrams: $request->integer('weight_grams'),
            customPricePerKilogramRupiah: $request->filled('custom_price_per_kilogram_rupiah')
                ? $request->integer('custom_price_per_kilogram_rupiah')
                : null,
        );
    }
}
