<?php

namespace App\Http\Resources;

use App\Data\Payments\GatewayPaymentMethodData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GatewayPaymentMethodData */
class PaymentGatewayMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'driver' => $this->driver->value,
            'driver_label' => $this->driver->label(),
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'fee_rupiah' => $this->feeRupiah,
            'minimum_rupiah' => $this->minimumRupiah,
            'maximum_rupiah' => $this->maximumRupiah,
            'is_default' => $this->isDefault,
        ];
    }
}
