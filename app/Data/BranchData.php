<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreBranchRequest;

final readonly class BranchData
{
    public function __construct(
        public int $organizationId,
        public int $cityId,
        public string $name,
        public string $code,
        public ?string $phone,
        public string $address,
        public ?float $latitude,
        public ?float $longitude,
        public int $freeDeliveryRadiusMeters,
        public int $deliveryFeePerKmRupiah,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreBranchRequest $request): self
    {
        return new self(
            organizationId: $request->organizationId(),
            cityId: $request->integer('city_id'),
            name: $request->string('name')->toString(),
            code: strtoupper($request->string('code')->toString()),
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            address: $request->string('address')->toString(),
            latitude: $request->filled('latitude') ? $request->float('latitude') : null,
            longitude: $request->filled('longitude') ? $request->float('longitude') : null,
            freeDeliveryRadiusMeters: $request->integer('free_delivery_radius_meters', 10000),
            deliveryFeePerKmRupiah: $request->integer('delivery_fee_per_km_rupiah'),
            isActive: $request->boolean('is_active', true),
        );
    }
}
