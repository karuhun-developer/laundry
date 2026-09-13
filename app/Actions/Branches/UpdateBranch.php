<?php

namespace App\Actions\Branches;

use App\Data\BranchData;
use App\Models\Branch;

final class UpdateBranch
{
    public function handle(Branch $branch, BranchData $data): Branch
    {
        $branch->update([
            'city_id' => $data->cityId,
            'name' => $data->name,
            'code' => $data->code,
            'phone' => $data->phone,
            'address' => $data->address,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'free_delivery_radius_meters' => $data->freeDeliveryRadiusMeters,
            'delivery_fee_per_km_rupiah' => $data->deliveryFeePerKmRupiah,
            'is_active' => $data->isActive,
        ]);

        return $branch->refresh();
    }
}
