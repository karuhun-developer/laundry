<?php

use App\Models\Branch;
use App\Services\DeliveryRouteCalculator;

it('calculates haversine distance in meters', function (): void {
    $distance = app(DeliveryRouteCalculator::class)->distanceMeters(
        -6.2607,
        106.8136,
        -6.1754,
        106.8272,
    );

    expect($distance)->toBeBetween(9500, 9700);
});

it('keeps pickup and delivery free inside the configured radius', function (): void {
    $branch = new Branch([
        'latitude' => -6.2607,
        'longitude' => 106.8136,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3000,
    ]);

    $quote = app(DeliveryRouteCalculator::class)->quote($branch, -6.2507, 106.8136);

    expect($quote->withinFreeRadius)->toBeTrue()
        ->and($quote->deliveryFeeRupiah)->toBe(0);
});

it('rounds excess distance up to each started kilometer', function (): void {
    $branch = new Branch([
        'latitude' => 0.0,
        'longitude' => 0.0,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3000,
    ]);

    $quote = app(DeliveryRouteCalculator::class)->quote($branch, 0.1, 0.0);

    expect($quote->distanceMeters)->toBeBetween(11100, 11130)
        ->and($quote->withinFreeRadius)->toBeFalse()
        ->and($quote->deliveryFeeRupiah)->toBe(6000);
});

it('keeps exactly ten kilometers free and charges just beyond it', function (): void {
    $branch = new Branch([
        'latitude' => 0.0,
        'longitude' => 0.0,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3000,
    ]);
    $calculator = app(DeliveryRouteCalculator::class);

    $exactBoundaryQuote = $calculator->quote($branch, 0.0, 0.08993216059187306);
    $overBoundaryQuote = $calculator->quote($branch, 0.0, 0.08994115380793225);

    expect($exactBoundaryQuote->distanceMeters)->toBe(10000)
        ->and($exactBoundaryQuote->withinFreeRadius)->toBeTrue()
        ->and($exactBoundaryQuote->deliveryFeeRupiah)->toBe(0)
        ->and($overBoundaryQuote->distanceMeters)->toBe(10001)
        ->and($overBoundaryQuote->withinFreeRadius)->toBeFalse()
        ->and($overBoundaryQuote->deliveryFeeRupiah)->toBe(3000);
});
