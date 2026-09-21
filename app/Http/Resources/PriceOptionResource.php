<?php

namespace App\Http\Resources;

use App\Enums\PriceType;
use App\Enums\RoleName;
use App\Models\PriceOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PriceOption */
class PriceOptionResource extends JsonResource
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
            'type' => $this->type->value,
            'pricing' => $this->type === PriceType::FixedPackage
                ? [
                    'package_weight_grams' => $this->package_weight_grams,
                    'base_price_rupiah' => $this->base_price_rupiah,
                    'excess_price_per_kilogram_rupiah' => $this->price_per_kilogram_rupiah,
                ]
                : [
                    'price_per_kilogram_rupiah' => $this->price_per_kilogram_rupiah,
                    ...($request->user()?->hasRole(RoleName::Customer->value)
                        ? []
                        : ['min_price_per_kilogram_rupiah' => $this->min_price_per_kilogram_rupiah ?? $this->price_per_kilogram_rupiah]),
                    'minimum_chargeable_grams' => $this->minimum_chargeable_grams,
                ],
        ];
    }
}
