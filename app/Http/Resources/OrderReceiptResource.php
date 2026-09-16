<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin Order */
class OrderReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paidRupiah = $this->payments
            ->filter(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid)
            ->sum('amount_rupiah');

        return [
            'receipt_number' => $this->order_number,
            'order_id' => $this->id,
            'order_status' => $this->status->value,
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
                'phone' => $this->branch->phone,
                'address' => $this->branch->address,
                'city' => [
                    'name' => $this->branch->city->name,
                    'province' => $this->branch->city->province,
                ],
            ],
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name_snapshot,
            ],
            'items' => OrderItemResource::collection($this->items),
            'pricing' => [
                'items_subtotal_rupiah' => $this->items_subtotal_rupiah,
                'delivery_fee_rupiah' => $this->delivery_fee_rupiah,
                'total_rupiah' => $this->total_rupiah,
                'paid_rupiah' => $paidRupiah,
                'outstanding_rupiah' => max(0, $this->total_rupiah - $paidRupiah),
            ],
            'payments' => PaymentResource::collection($this->payments),
            'issued_at' => now()->toISOString(),
            'ordered_at' => $this->created_at->toISOString(),
            'share_url' => URL::signedRoute(
                'orders.public.show',
                ['order' => $this->order_number],
            ),
        ];
    }
}
