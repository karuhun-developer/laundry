<?php

namespace App\Data;

use App\Models\PriceOption;

final readonly class PriceQuoteData
{
    public function __construct(
        public PriceOption $priceOption,
        public int $requestedWeightGrams,
        public int $chargeableWeightGrams,
        public int $basePriceRupiah,
        public int $excessWeightGrams,
        public int $excessPriceRupiah,
        public int $totalRupiah,
        public ?int $effectivePricePerKilogramRupiah = null,
    ) {}
}
