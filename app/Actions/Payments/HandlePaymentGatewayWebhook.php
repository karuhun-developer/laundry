<?php

namespace App\Actions\Payments;

use App\Data\Payments\PaymentGatewayWebhookData;
use App\Data\Payments\PaymentGatewayWebhookResultData;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\PaymentGatewayDelivery;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

final readonly class HandlePaymentGatewayWebhook
{
    public function __construct(
        private PaymentGatewayManager $paymentGatewayManager,
    ) {}

    public function handle(
        PaymentGatewayDriver $driver,
        PaymentGatewayWebhookData $data,
    ): PaymentGatewayWebhookResultData {
        $gateway = $this->paymentGatewayManager->driver($driver);

        if (! $gateway->verifyWebhook($data->rawBody, $data->signature)) {
            throw PaymentGatewayException::invalidWebhook();
        }

        if ($data->headerEvent !== $data->bodyEvent) {
            throw PaymentGatewayException::webhookDataMismatch(
                'Nama event webhook pada header dan isi request tidak sama.',
            );
        }

        $this->validateEventStatus($data);

        return DB::transaction(function () use ($driver, $data): PaymentGatewayWebhookResultData {
            $payment = Payment::query()
                ->where('gateway_driver', $driver->value)
                ->where('gateway_order_id', $data->gatewayOrderId)
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof Payment) {
                throw PaymentGatewayException::webhookPaymentNotFound();
            }

            $this->validatePayment($payment, $data);

            $delivery = PaymentGatewayDelivery::query()->firstOrCreate(
                [
                    'gateway_driver' => $driver,
                    'delivery_id' => $data->deliveryId,
                ],
                [
                    'payment_id' => $payment->id,
                    'event' => $data->bodyEvent,
                    'payload' => $data->payload,
                    'processed_at' => now(),
                ],
            );

            if (! $delivery->wasRecentlyCreated) {
                if (
                    $delivery->payment_id !== $payment->id
                    || $delivery->event !== $data->bodyEvent
                ) {
                    throw PaymentGatewayException::webhookDataMismatch(
                        'ID pengiriman webhook sudah digunakan untuk event lain.',
                    );
                }

                return new PaymentGatewayWebhookResultData(
                    duplicate: true,
                    paymentId: $payment->id,
                    status: $payment->status,
                );
            }

            $currentStatus = $payment->status;
            $nextStatus = $this->nextStatus($payment, $data);
            $previousGatewayStatus = $payment->gateway_status;
            $providerStatusChanged = $payment->gateway_status !== $data->providerStatus;
            $gatewayPayload = $payment->gateway_payload ?? [];

            $payment->update([
                'status' => $nextStatus,
                'gateway_status' => $data->providerStatus,
                'gateway_payload' => [
                    ...$gatewayPayload,
                    'last_webhook' => $data->payload,
                ],
                'settled_at' => $nextStatus === PaymentStatus::Paid
                    ? ($payment->settled_at ?? $data->paidAt ?? now())
                    : $payment->settled_at,
            ]);

            if ($nextStatus !== $currentStatus) {
                $payment->statusEvents()->create([
                    'actor_id' => null,
                    'actor_name_snapshot' => $driver->label(),
                    'status' => $nextStatus,
                    'note' => "Status diperbarui oleh webhook {$driver->label()}.",
                ]);
            }

            if ($providerStatusChanged) {
                activity('payments')
                    ->performedOn($payment)
                    ->withProperties([
                        'gateway_driver' => $driver->value,
                        'delivery_id' => $data->deliveryId,
                        'event' => $data->bodyEvent,
                        'from_gateway_status' => $previousGatewayStatus,
                        'to_gateway_status' => $data->providerStatus,
                        'from_status' => $currentStatus->value,
                        'to_status' => $nextStatus->value,
                    ])
                    ->log('payment gateway status updated');
            }

            return new PaymentGatewayWebhookResultData(
                duplicate: false,
                paymentId: $payment->id,
                status: $nextStatus,
            );
        });
    }

    private function validatePayment(
        Payment $payment,
        PaymentGatewayWebhookData $data,
    ): void {
        if ($payment->gateway_payment_id !== $data->providerPaymentId) {
            throw PaymentGatewayException::webhookDataMismatch(
                'ID pembayaran dari webhook tidak sesuai.',
            );
        }

        if ($payment->amount_rupiah !== $data->amountRupiah) {
            throw PaymentGatewayException::webhookDataMismatch(
                'Nominal pembayaran dari webhook tidak sesuai.',
            );
        }

        if (
            $data->paymentMethod !== null
            && $payment->gateway_method !== $data->paymentMethod
        ) {
            throw PaymentGatewayException::webhookDataMismatch(
                'Metode pembayaran dari webhook tidak sesuai.',
            );
        }
    }

    private function nextStatus(
        Payment $payment,
        PaymentGatewayWebhookData $data,
    ): PaymentStatus {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment->status;
        }

        return match ($data->bodyEvent) {
            'transaction.settlement' => PaymentStatus::Pending,
            'transaction.paid' => PaymentStatus::Paid,
            'transaction.failed' => PaymentStatus::Failed,
            'transaction.cancelled' => PaymentStatus::Cancelled,
            default => throw PaymentGatewayException::webhookDataMismatch(
                'Event pembayaran dari webhook tidak dikenali.',
            ),
        };
    }

    private function validateEventStatus(PaymentGatewayWebhookData $data): void
    {
        $expectedStatus = match ($data->bodyEvent) {
            'transaction.settlement' => 'settlement',
            'transaction.paid' => 'success',
            'transaction.failed' => 'failed',
            'transaction.cancelled' => 'cancelled',
            default => null,
        };

        if ($expectedStatus === null || $data->providerStatus !== $expectedStatus) {
            throw PaymentGatewayException::webhookDataMismatch(
                'Event dan status pembayaran dari webhook tidak sesuai.',
            );
        }
    }
}
