<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class MobileBranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => [
                'name' => $this->city->name,
                'province' => $this->city->province,
            ],
            'coordinates' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'delivery' => [
                'free_radius_meters' => $this->free_delivery_radius_meters,
                'fee_per_km_rupiah' => $this->delivery_fee_per_km_rupiah,
            ],
        ];
    }
}
