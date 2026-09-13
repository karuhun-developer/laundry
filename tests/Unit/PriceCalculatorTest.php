<?php

use App\Enums\PriceType;
use App\Exceptions\PriceCannotBeQuoted;
use App\Models\PriceOption;
use App\Services\PriceCalculator;

it('charges the fixed package base price while weight is inside the package limit', function (): void {
    $priceOption = new PriceOption([
        'type' => PriceType::FixedPackage,
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
        'price_per_kilogram_rupiah' => 12000,
    ]);

    $quote = app(PriceCalculator::class)->quote($priceOption, 11500);

    expect($quote->chargeableWeightGrams)->toBe(12000)
        ->and($quote->excessWeightGrams)->toBe(0)
        ->and($quote->totalRupiah)->toBe(120000);
});

it('adds proportional excess pricing to an overweight fixed package', function (): void {
    $priceOption = new PriceOption([
        'type' => PriceType::FixedPackage,
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
        'price_per_kilogram_rupiah' => 12000,
    ]);

    $quote = app(PriceCalculator::class)->quote($priceOption, 12500);

    expect($quote->excessWeightGrams)->toBe(500)
        ->and($quote->excessPriceRupiah)->toBe(6000)
        ->and($quote->totalRupiah)->toBe(126000);
});

it('rejects an overweight package without an excess tariff', function (): void {
    $priceOption = new PriceOption([
        'type' => PriceType::FixedPackage,
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
        'price_per_kilogram_rupiah' => null,
    ]);

    app(PriceCalculator::class)->quote($priceOption, 12500);
})->throws(PriceCannotBeQuoted::class);

it('calculates a proportional per-kilogram price using integer half-up rounding', function (): void {
    $priceOption = new PriceOption([
        'type' => PriceType::PerKilogram,
        'price_per_kilogram_rupiah' => 999,
    ]);

    $quote = app(PriceCalculator::class)->quote($priceOption, 1501);

    expect($quote->chargeableWeightGrams)->toBe(1501)
        ->and($quote->totalRupiah)->toBe(1499);
});

it('applies a minimum chargeable weight to per-kilogram pricing', function (): void {
    $priceOption = new PriceOption([
        'type' => PriceType::PerKilogram,
        'price_per_kilogram_rupiah' => 12000,
        'minimum_chargeable_grams' => 3000,
    ]);

    $quote = app(PriceCalculator::class)->quote($priceOption, 1250);

    expect($quote->chargeableWeightGrams)->toBe(3000)
        ->and($quote->totalRupiah)->toBe(36000);
});
