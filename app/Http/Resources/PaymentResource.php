<?php

namespace App\Http\Resources;

use App\Models\Payment;
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
            'note' => $this->note,
            'settled_at' => $this->settled_at?->toISOString(),
            'status_history' => PaymentStatusEventResource::collection(
                $this->whenLoaded('statusEvents'),
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
