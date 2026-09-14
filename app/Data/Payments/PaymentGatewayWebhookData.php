<?php

namespace App\Data\Payments;

use App\Http\Requests\Api\V1\StorePaymentGatewayWebhookRequest;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class PaymentGatewayWebhookData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $signature,
        public string $headerEvent,
        public string $deliveryId,
        public string $rawBody,
        public string $bodyEvent,
        public string $providerPaymentId,
        public string $gatewayOrderId,
        public int $amountRupiah,
        public ?string $paymentMethod,
        public string $providerStatus,
        public ?CarbonImmutable $paidAt,
        public array $payload,
    ) {}

    public static function fromRequest(StorePaymentGatewayWebhookRequest $request): self
    {
        $signature = $request->validated('_signature');
        $headerEvent = $request->validated('_header_event');
        $deliveryId = $request->validated('_delivery_id');
        $bodyEvent = $request->validated('event');
        $providerPaymentId = $request->validated('data.id');
        $gatewayOrderId = $request->validated('data.orderId');
        $amountRupiah = $request->validated('data.amount');
        $paymentMethod = $request->validated('data.paymentMethod');
        $providerStatus = $request->validated('data.status');
        $paidAt = $request->validated('data.paidAt');
        $payload = $request->json()->all();

        if (
            ! is_string($signature)
            || ! is_string($headerEvent)
            || ! is_string($deliveryId)
            || ! is_string($bodyEvent)
            || ! is_string($providerPaymentId)
            || ! is_string($gatewayOrderId)
            || ! is_int($amountRupiah)
            || ($paymentMethod !== null && ! is_string($paymentMethod))
            || ! is_string($providerStatus)
            || ($paidAt !== null && ! is_string($paidAt))
        ) {
            throw new LogicException('Validated payment gateway webhook has an unexpected type.');
        }

        return new self(
            signature: $signature,
            headerEvent: $headerEvent,
            deliveryId: $deliveryId,
            rawBody: $request->getContent(),
            bodyEvent: $bodyEvent,
            providerPaymentId: $providerPaymentId,
            gatewayOrderId: $gatewayOrderId,
            amountRupiah: $amountRupiah,
            paymentMethod: $paymentMethod === null ? null : strtoupper(trim($paymentMethod)),
            providerStatus: strtolower(trim($providerStatus)),
            paidAt: $paidAt === null ? null : CarbonImmutable::parse($paidAt),
            payload: $payload,
        );
    }
}
