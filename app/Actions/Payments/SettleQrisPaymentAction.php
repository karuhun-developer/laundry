<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Cashflow\RecordPaymentCashflow;
use App\Actions\PushNotifications\DispatchOrderPushNotification;
use App\Enums\PaymentStatus;
use App\Enums\PushNotificationEvent;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class SettleQrisPaymentAction
{
    public function __construct(
        private DispatchOrderPushNotification $dispatchOrderPushNotification,
    ) {}

    public function handle(Payment $payment, ?CarbonInterface $settledAt = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $settledAt, $note): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === PaymentStatus::Paid) {
                return $lockedPayment;
            }

            $lockedOrder = Order::query()->whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            $settledTime = $settledAt instanceof CarbonInterface
                ? $settledAt
                : CarbonImmutable::now();

            $lockedPayment->update([
                'status' => PaymentStatus::Paid,
                'settled_at' => $settledTime,
            ]);

            $lockedPayment->statusEvents()->create([
                'actor_id' => null,
                'actor_name_snapshot' => 'Gateway QRIS Dinamis',
                'status' => PaymentStatus::Paid,
                'note' => $note ?? 'Pembayaran QRIS berhasil dikonfirmasi oleh sistem gateway.',
            ]);

            activity('payments')
                ->performedOn($lockedPayment)
                ->withProperties([
                    'from_status' => PaymentStatus::Pending->value,
                    'to_status' => PaymentStatus::Paid->value,
                    'qris_token' => $lockedPayment->qris_token,
                    'note' => $note ?? 'Pembayaran QRIS berhasil diverifikasi',
                ])
                ->log('payment settled via dynamic qris api');

            app(RecordPaymentCashflow::class)->handle($lockedPayment);

            $this->dispatchOrderPushNotification->handle(
                $lockedOrder,
                PushNotificationEvent::PaymentVerified,
            );

            return $lockedPayment->load('statusEvents');
        });
    }
}
