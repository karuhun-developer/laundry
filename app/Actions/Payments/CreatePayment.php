<?php

namespace App\Actions\Payments;

use App\Data\CreatePaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentCannotBeCreated;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Qris\QrisPayloadGenerator;
use Illuminate\Support\Facades\DB;

final readonly class CreatePayment
{
    public function __construct(private QrisPayloadGenerator $qrisPayloadGenerator) {}

    public function handle(
        Order $order,
        Branch $branch,
        User $actor,
        CreatePaymentData $data,
    ): Payment {
        return DB::transaction(function () use ($order, $branch, $actor, $data): Payment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $paidRupiah = (int) $lockedOrder->payments()
                ->where('status', PaymentStatus::Paid)
                ->sum('amount_rupiah');
            $outstandingRupiah = max(0, $lockedOrder->total_rupiah - $paidRupiah);

            if ($outstandingRupiah === 0) {
                throw PaymentCannotBeCreated::orderAlreadyPaid();
            }

            if ($data->amountRupiah > $outstandingRupiah) {
                throw PaymentCannotBeCreated::amountExceedsOutstanding($outstandingRupiah);
            }

            if ($lockedOrder->payments()->where('status', PaymentStatus::Pending)->exists()) {
                throw PaymentCannotBeCreated::pendingPaymentExists();
            }

            $status = $data->method === PaymentMethod::Cash
                ? PaymentStatus::Paid
                : PaymentStatus::Pending;
            $qrisPayload = null;

            if ($data->method === PaymentMethod::Qris) {
                if (! $branch->qris_enabled || $branch->qris_static_payload === null) {
                    throw PaymentCannotBeCreated::qrisUnavailable();
                }

                $qrisPayload = $this->qrisPayloadGenerator->generate(
                    $branch->qris_static_payload,
                    $data->amountRupiah,
                );
            }

            $payment = Payment::query()->create([
                'organization_id' => $lockedOrder->organization_id,
                'branch_id' => $lockedOrder->branch_id,
                'order_id' => $lockedOrder->id,
                'recorded_by_id' => $actor->id,
                'method' => $data->method,
                'status' => $status,
                'amount_rupiah' => $data->amountRupiah,
                'qris_payload' => $qrisPayload,
                'note' => $data->note,
                'settled_at' => $status === PaymentStatus::Paid ? now() : null,
            ]);

            $payment->forceFill([
                'payment_number' => sprintf(
                    'PAY-%s-%08d',
                    $payment->created_at->format('Ymd'),
                    $payment->id,
                ),
            ])->save();

            $payment->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => $status,
                'note' => $data->note,
            ]);

            activity('payments')
                ->causedBy($actor)
                ->performedOn($payment)
                ->withProperties([
                    'payment_number' => $payment->payment_number,
                    'order_id' => $lockedOrder->id,
                    'method' => $data->method->value,
                    'status' => $status->value,
                    'amount_rupiah' => $data->amountRupiah,
                ])
                ->log('payment created');

            return $payment->load('statusEvents');
        });
    }
}
