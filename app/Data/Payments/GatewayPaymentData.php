<?php

namespace App\Data\Payments;

use Carbon\CarbonImmutable;

final readonly class GatewayPaymentData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $orderId,
        public int $amountRupiah,
        public int $totalPaymentRupiah,
        public string $paymentMethod,
        public string $paymentNumber,
        public ?string $paymentUrl,
        public string $status,
        public ?CarbonImmutable $expiresAt,
        public array $payload,
    ) {}
}
