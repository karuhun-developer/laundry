<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Services\Qris\QrisImageGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
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
            'payment_number' => $this->payment_number,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount_rupiah' => $this->amount_rupiah,
            'qris_payload' => $this->qris_payload,
            'qris_image_data_uri' => $this->qris_payload === null
                ? null
                : (new QrisImageGenerator)->dataUri($this->qris_payload),
            'gateway' => $this->gateway_driver === null
                ? null
                : [
                    'driver' => $this->gateway_driver->value,
                    'driver_label' => $this->gateway_driver->label(),
                    'payment_id' => $this->gateway_payment_id,
                    'order_id' => $this->gateway_order_id,
                    'method' => $this->gateway_method,
                    'status' => $this->gateway_status,
                    'payment_number' => $this->gateway_payment_number,
                    'payment_url' => $this->gateway_payment_url,
                    'expires_at' => $this->gateway_expires_at?->toISOString(),
                ],
            'note' => $this->note,
            'settled_at' => $this->settled_at?->toISOString(),
            'status_history' => PaymentStatusEventResource::collection(
                $this->whenLoaded('statusEvents'),
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
