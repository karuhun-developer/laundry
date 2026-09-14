<?php

namespace App\Actions\Payments;

use App\Data\Payments\CreateGatewayPaymentData;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;

final class CreateGatewayPayment
{
    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager,
    ) {}

    public function handle(
        Payment $payment,
        PaymentGatewayDriver $driver,
        string $gatewayMethod,
    ): Payment {
        $normalizedMethod = strtoupper($gatewayMethod);

        if (in_array($normalizedMethod, ['VA', 'ALL'], true)) {
            throw PaymentGatewayException::invalidMethod($normalizedMethod);
        }

        $gateway = $this->paymentGatewayManager->driver($driver);

        if (! $gateway->isAvailable()) {
            throw PaymentGatewayException::notConfigured($driver->label());
        }

        $availableMethod = null;

        foreach ($gateway->methods() as $method) {
            if ($method->code === $normalizedMethod) {
                $availableMethod = $method;
                break;
            }
        }

        if ($availableMethod === null) {
            throw PaymentGatewayException::invalidMethod($normalizedMethod);
        }

        if (
            $payment->amount_rupiah < $availableMethod->minimumRupiah
            || $payment->amount_rupiah > $availableMethod->maximumRupiah
        ) {
            throw PaymentGatewayException::amountOutsideMethodLimits(
                $normalizedMethod,
                $availableMethod->minimumRupiah,
                $availableMethod->maximumRupiah,
            );
        }

        $result = $gateway->createPayment(new CreateGatewayPaymentData(
            orderId: (string) $payment->payment_number,
            amountRupiah: $payment->amount_rupiah,
            paymentMethod: $normalizedMethod,
            metadata: [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'organization_id' => $payment->organization_id,
                'branch_id' => $payment->branch_id,
            ],
        ));

        if (
            $result->orderId !== $payment->payment_number
            || $result->amountRupiah !== $payment->amount_rupiah
            || $result->paymentMethod !== $normalizedMethod
        ) {
            throw PaymentGatewayException::invalidProviderResponse($driver->label());
        }

        $providerStatus = strtolower(trim($result->status));
        $status = match ($providerStatus) {
            'pending', 'settlement' => PaymentStatus::Pending,
            'success' => PaymentStatus::Paid,
            'failed' => PaymentStatus::Failed,
            'cancelled' => PaymentStatus::Cancelled,
            default => throw PaymentGatewayException::invalidProviderResponse($driver->label()),
        };

        $payment->update([
            'status' => $status,
            'qris_payload' => $normalizedMethod === 'QRIS' ? $result->paymentNumber : null,
            'settled_at' => $status === PaymentStatus::Paid ? now() : null,
            'gateway_driver' => $driver,
            'gateway_payment_id' => $result->id,
            'gateway_order_id' => $result->orderId,
            'gateway_method' => $result->paymentMethod,
            'gateway_status' => $providerStatus,
            'gateway_payment_number' => $result->paymentNumber,
            'gateway_payment_url' => $result->paymentUrl,
            'gateway_expires_at' => $result->expiresAt,
            'gateway_payload' => ['transaction' => $result->payload],
        ]);

        return $payment->refresh();
    }
}
