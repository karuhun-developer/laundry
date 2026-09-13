<?php

namespace App\Services;

use App\Data\DeliveryQuoteData;
use App\Models\Branch;
use InvalidArgumentException;

final class DeliveryRouteCalculator
{
    private const float EARTH_RADIUS_METERS = 6371000.0;

    public function quote(Branch $branch, float $latitude, float $longitude): DeliveryQuoteData
    {
        if ($branch->latitude === null || $branch->longitude === null) {
            throw new InvalidArgumentException('Branch coordinates are required for a delivery quote.');
        }

        $distanceMeters = $this->distanceMeters(
            $branch->latitude,
            $branch->longitude,
            $latitude,
            $longitude,
        );
        $excessMeters = max(0, $distanceMeters - $branch->free_delivery_radius_meters);
        $chargeableKilometers = (int) ceil($excessMeters / 1000);

        return new DeliveryQuoteData(
            distanceMeters: $distanceMeters,
            deliveryFeeRupiah: $chargeableKilometers * $branch->delivery_fee_per_km_rupiah,
            withinFreeRadius: $excessMeters === 0,
        );
    }

    public function distanceMeters(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude,
    ): int {
        $originLatitudeRadians = deg2rad($originLatitude);
        $destinationLatitudeRadians = deg2rad($destinationLatitude);
        $latitudeDelta = deg2rad($destinationLatitude - $originLatitude);
        $longitudeDelta = deg2rad($destinationLongitude - $originLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($originLatitudeRadians) * cos($destinationLatitudeRadians)
            * sin($longitudeDelta / 2) ** 2;
        $normalizedA = min(1.0, max(0.0, $a));
        $arc = 2 * atan2(sqrt($normalizedA), sqrt(1 - $normalizedA));

        return (int) round(self::EARTH_RADIUS_METERS * $arc);
    }
}
