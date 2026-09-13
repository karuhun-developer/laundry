<?php

namespace App\Data;

final readonly class DeliveryQuoteData
{
    public function __construct(
        public int $distanceMeters,
        public int $deliveryFeeRupiah,
        public bool $withinFreeRadius,
    ) {}
}
