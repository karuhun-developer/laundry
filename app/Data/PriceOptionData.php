<?php

namespace App\Data;

use App\Enums\PriceType;
use App\Http\Requests\Cms\StorePriceOptionRequest;
use App\Http\Requests\Cms\UpdatePriceOptionRequest;

final readonly class PriceOptionData
{
    public function __construct(
        public ?int $branchId,
        public PriceType $type,
        public string $name,
        public ?int $packageWeightGrams,
        public ?int $basePriceRupiah,
        public ?int $pricePerKilogramRupiah,
        public ?int $minPricePerKilogramRupiah,
        public ?int $minimumChargeableGrams,
        public bool $isActive,
    ) {}

    public static function fromRequest(StorePriceOptionRequest|UpdatePriceOptionRequest $request): self
    {
        return new self(
            branchId: $request->filled('branch_id') ? $request->integer('branch_id') : null,
            type: PriceType::from($request->string('type')->toString()),
            name: $request->string('name')->toString(),
            packageWeightGrams: $request->filled('package_weight_grams')
                ? $request->integer('package_weight_grams')
                : null,
            basePriceRupiah: $request->filled('base_price_rupiah')
                ? $request->integer('base_price_rupiah')
                : null,
            pricePerKilogramRupiah: $request->filled('price_per_kilogram_rupiah')
                ? $request->integer('price_per_kilogram_rupiah')
                : null,
            minPricePerKilogramRupiah: $request->filled('min_price_per_kilogram_rupiah')
                ? $request->integer('min_price_per_kilogram_rupiah')
                : null,
            minimumChargeableGrams: $request->filled('minimum_chargeable_grams')
                ? $request->integer('minimum_chargeable_grams')
                : null,
            isActive: $request->boolean('is_active', true),
        );
    }
}
