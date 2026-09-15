<?php

namespace App\Actions\Branches;

use App\Data\BranchData;
use App\Models\Branch;

final class CreateBranch
{
    public function handle(BranchData $data): Branch
    {
        return Branch::query()->create([
            'organization_id' => $data->organizationId,
            'city_id' => $data->cityId,
            'name' => $data->name,
            'code' => $data->code,
            'phone' => $data->phone,
            'address' => $data->address,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'free_delivery_radius_meters' => $data->freeDeliveryRadiusMeters,
            'delivery_fee_per_km_rupiah' => $data->deliveryFeePerKmRupiah,
            'qris_enabled' => $data->qrisEnabled,
            'qris_static_payload' => $data->qrisStaticPayload,
            'is_active' => $data->isActive,
        ]);
    }
}
