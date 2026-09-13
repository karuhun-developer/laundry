<?php

namespace App\Actions\Payments;

use App\Data\TransitionPaymentStatusData;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentStatusTransition;
use App\Exceptions\PaymentCannotBeCreated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class TransitionPaymentStatus
{
    public function handle(
        Order $order,
        Payment $payment,
        User $actor,
        TransitionPaymentStatusData $data,
    ): Payment {
        return DB::transaction(function () use ($order, $payment, $actor, $data): Payment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedPayment->status;

            if (! $currentStatus->canTransitionTo($data->status)) {
                throw InvalidPaymentStatusTransition::between($currentStatus, $data->status);
            }

            if ($data->status === PaymentStatus::Paid) {
                $paidRupiah = (int) $lockedOrder->payments()
                    ->whereKeyNot($lockedPayment->id)
                    ->where('status', PaymentStatus::Paid)
                    ->sum('amount_rupiah');
                $outstandingRupiah = max(0, $lockedOrder->total_rupiah - $paidRupiah);

                if ($lockedPayment->amount_rupiah > $outstandingRupiah) {
                    throw PaymentCannotBeCreated::amountExceedsOutstanding($outstandingRupiah);
                }
            }

            $lockedPayment->update([
                'status' => $data->status,
                'settled_at' => $data->status === PaymentStatus::Paid
                    ? now()
                    : $lockedPayment->settled_at,
            ]);
            $lockedPayment->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => $data->status,
                'note' => $data->note,
            ]);

            activity('payments')
                ->causedBy($actor)
                ->performedOn($lockedPayment)
                ->withProperties([
                    'from_status' => $currentStatus->value,
                    'to_status' => $data->status->value,
                    'note' => $data->note,
                ])
                ->log('payment status transitioned');

            return $lockedPayment->load('statusEvents');
        });
    }
}
