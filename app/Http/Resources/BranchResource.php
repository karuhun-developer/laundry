<?php

namespace App\Http\Resources;

use App\Data\NearestBranchData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var NearestBranchData $nearestBranch */
        $nearestBranch = $this->resource;
        $branch = $nearestBranch->branch;
        $quote = $nearestBranch->deliveryQuote;

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'phone' => $branch->phone,
            'address' => $branch->address,
            'city' => [
                'id' => $branch->city->id,
                'name' => $branch->city->name,
                'province' => $branch->city->province,
            ],
            'coordinates' => [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
            ],
            'delivery' => [
                'distance_meters' => $quote->distanceMeters,
                'within_free_radius' => $quote->withinFreeRadius,
                'free_radius_meters' => $branch->free_delivery_radius_meters,
                'fee_per_km_rupiah' => $branch->delivery_fee_per_km_rupiah,
                'fee_rupiah' => $quote->deliveryFeeRupiah,
            ],
        ];
    }
}
