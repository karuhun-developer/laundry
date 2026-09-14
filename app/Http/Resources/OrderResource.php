<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paidRupiah = $this->relationLoaded('payments')
            ? $this->payments
                ->filter(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid)
                ->sum('amount_rupiah')
            : 0;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'organization_id' => $this->organization_id,
            'organization' => [
                'id' => $this->organization_id,
                'slug' => $this->organization->slug,
            ],
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
                'qris_enabled' => $this->branch->qris_enabled,
            ],
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name_snapshot,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'pricing' => [
                'items_subtotal_rupiah' => $this->items_subtotal_rupiah,
                'delivery_fee_rupiah' => $this->delivery_fee_rupiah,
                'total_rupiah' => $this->total_rupiah,
            ],
            'delivery' => $this->customer_latitude === null
                ? null
                : [
                    'customer_latitude' => $this->customer_latitude,
                    'customer_longitude' => $this->customer_longitude,
                    'distance_meters' => $this->delivery_distance_meters,
                    'free_radius_meters_snapshot' => $this->delivery_free_radius_meters_snapshot,
                    'fee_per_km_rupiah_snapshot' => $this->delivery_fee_per_km_rupiah_snapshot,
                    'fee_rupiah' => $this->delivery_fee_rupiah,
                ],
            'status_history' => OrderStatusEventResource::collection(
                $this->whenLoaded('statusEvents'),
            ),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payment_summary' => $this->when($this->relationLoaded('payments'), [
                'paid_rupiah' => $paidRupiah,
                'outstanding_rupiah' => max(0, $this->total_rupiah - $paidRupiah),
                'is_paid' => $paidRupiah >= $this->total_rupiah,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
