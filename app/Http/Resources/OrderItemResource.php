<?php

namespace App\Http\Resources;

use App\Enums\RoleName;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
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
            'service' => [
                'id' => $this->service_id,
                'name' => $this->service_name_snapshot,
                'code' => $this->service_code_snapshot,
            ],
            'price_option' => [
                'id' => $this->price_option_id,
                'name' => $this->price_option_name_snapshot,
                'type' => $this->price_type_snapshot->value,
                'pricing_snapshot' => [
                    'package_weight_grams' => $this->package_weight_grams_snapshot,
                    'base_price_rupiah' => $this->base_price_rupiah_snapshot,
                    'price_per_kilogram_rupiah' => $this->price_per_kilogram_rupiah_snapshot,
                    'min_price_per_kilogram_rupiah' => $request->user()?->hasRole(RoleName::Customer->value)
                        ? null
                        : $this->min_price_per_kilogram_rupiah_snapshot,
                    'minimum_chargeable_grams' => $this->minimum_chargeable_grams_snapshot,
                ],
            ],
            'calculation' => [
                'requested_weight_grams' => $this->requested_weight_grams,
                'chargeable_weight_grams' => $this->chargeable_weight_grams,
                'base_price_rupiah' => $this->calculated_base_price_rupiah,
                'excess_weight_grams' => $this->excess_weight_grams,
                'excess_price_rupiah' => $this->excess_price_rupiah,
                'subtotal_rupiah' => $this->subtotal_rupiah,
            ],
        ];
    }
}
