<?php

namespace App\Services;

use App\Data\PriceQuoteData;
use App\Enums\PriceType;
use App\Exceptions\PriceCannotBeQuoted;
use App\Models\PriceOption;
use LogicException;

final class PriceCalculator
{
    public function quote(PriceOption $priceOption, int $weightGrams): PriceQuoteData
    {
        if ($weightGrams < 1) {
            throw new LogicException('Weight must be a positive integer of grams.');
        }

        return match ($priceOption->type) {
            PriceType::FixedPackage => $this->quoteFixedPackage($priceOption, $weightGrams),
            PriceType::PerKilogram => $this->quotePerKilogram($priceOption, $weightGrams),
        };
    }

    private function quoteFixedPackage(PriceOption $priceOption, int $weightGrams): PriceQuoteData
    {
        $packageWeightGrams = $priceOption->package_weight_grams
            ?? throw new LogicException('A fixed package requires a package weight.');
        $basePriceRupiah = $priceOption->base_price_rupiah
            ?? throw new LogicException('A fixed package requires a base price.');
        $excessWeightGrams = max(0, $weightGrams - $packageWeightGrams);

        if ($excessWeightGrams > 0 && $priceOption->price_per_kilogram_rupiah === null) {
            throw PriceCannotBeQuoted::missingPackageExcessRate();
        }

        $excessPriceRupiah = $excessWeightGrams === 0
            ? 0
            : $this->rupiahForGrams(
                $excessWeightGrams,
                $priceOption->price_per_kilogram_rupiah ?? 0,
            );

        return new PriceQuoteData(
            priceOption: $priceOption,
            requestedWeightGrams: $weightGrams,
            chargeableWeightGrams: max($weightGrams, $packageWeightGrams),
            basePriceRupiah: $basePriceRupiah,
            excessWeightGrams: $excessWeightGrams,
            excessPriceRupiah: $excessPriceRupiah,
            totalRupiah: $basePriceRupiah + $excessPriceRupiah,
        );
    }

    private function quotePerKilogram(PriceOption $priceOption, int $weightGrams): PriceQuoteData
    {
        $pricePerKilogramRupiah = $priceOption->price_per_kilogram_rupiah
            ?? throw new LogicException('A per-kilogram option requires a kilogram price.');
        $chargeableWeightGrams = max(
            $weightGrams,
            $priceOption->minimum_chargeable_grams ?? 0,
        );
        $totalRupiah = $this->rupiahForGrams(
            $chargeableWeightGrams,
            $pricePerKilogramRupiah,
        );

        return new PriceQuoteData(
            priceOption: $priceOption,
            requestedWeightGrams: $weightGrams,
            chargeableWeightGrams: $chargeableWeightGrams,
            basePriceRupiah: 0,
            excessWeightGrams: 0,
            excessPriceRupiah: 0,
            totalRupiah: $totalRupiah,
        );
    }

    private function rupiahForGrams(int $grams, int $pricePerKilogramRupiah): int
    {
        return intdiv(($grams * $pricePerKilogramRupiah) + 500, 1000);
    }
}
