<?php

namespace App\Contracts\Payments;

use App\Data\Payments\CreateGatewayPaymentData;
use App\Data\Payments\GatewayPaymentData;
use App\Data\Payments\GatewayPaymentMethodData;
use App\Data\Payments\GatewayPaymentStatusData;
use App\Enums\PaymentGatewayDriver;

interface PaymentGateway
{
    public function driver(): PaymentGatewayDriver;

    public function isAvailable(): bool;

    /** @return list<GatewayPaymentMethodData> */
    public function methods(): array;

    public function createPayment(CreateGatewayPaymentData $data): GatewayPaymentData;

    public function getPaymentStatus(string $gatewayOrderId): GatewayPaymentStatusData;

    public function verifyWebhook(string $rawBody, string $signature): bool;
}
