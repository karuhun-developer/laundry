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
    public function __construct(
        private QrisPayloadGenerator $qrisPayloadGenerator,
        private CreateGatewayPayment $createGatewayPayment,
    ) {}

    public function handle(
        Order $order,
        Branch $branch,
        ?User $actor,
        CreatePaymentData $data,
    ): Payment {
        return DB::transaction(function () use ($order, $branch, $actor, $data): Payment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->priced_at === null) {
                throw PaymentCannotBeCreated::pricingNotConfirmed();
            }

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

            $pendingPayments = $lockedOrder->payments()->where('status', PaymentStatus::Pending)->get();

            if ($pendingPayments->isNotEmpty()) {
                if ($data->method === PaymentMethod::Cash) {
                    foreach ($pendingPayments as $pendingPayment) {
                        $pendingPayment->update(['status' => PaymentStatus::Cancelled]);
                        $pendingPayment->statusEvents()->create([
                            'actor_id' => $actor?->id,
                            'actor_name_snapshot' => $actor instanceof User ? $actor->name : 'Kasir cabang',
                            'status' => PaymentStatus::Cancelled,
                            'note' => 'Dibatalkan karena kasir menerima pembayaran tunai.',
                        ]);
                    }
                } else {
                    throw PaymentCannotBeCreated::pendingPaymentExists();
                }
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
                'recorded_by_id' => $actor?->id,
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

            if ($data->method === PaymentMethod::Gateway) {
                if ($data->gatewayDriver === null || $data->gatewayMethod === null) {
                    throw new \LogicException('Gateway payment details are required.');
                }

                $payment = $this->createGatewayPayment->handle(
                    payment: $payment,
                    driver: $data->gatewayDriver,
                    gatewayMethod: $data->gatewayMethod,
                );
                $status = $payment->status;
            }

            $actorName = $actor instanceof User ? $actor->name : ($lockedOrder->customer_name_snapshot ?? 'Pelanggan');

            $payment->statusEvents()->create([
                'actor_id' => $actor?->id,
                'actor_name_snapshot' => $actorName,
                'status' => $payment->status,
                'note' => $data->note,
            ]);

            $activity = activity('payments');

            if ($actor !== null) {
                $activity->causedBy($actor);
            }

            $activity
                ->performedOn($payment)
                ->withProperties([
                    'payment_number' => $payment->payment_number,
                    'order_id' => $lockedOrder->id,
                    'method' => $data->method->value,
                    'status' => $payment->status->value,
                    'amount_rupiah' => $data->amountRupiah,
                ])
                ->log('payment created');

            return $payment->load('statusEvents');
        });
    }
}
