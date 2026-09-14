<?php

namespace App\Data\Payments;

use Carbon\CarbonImmutable;

final readonly class GatewayPaymentStatusData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $orderId,
        public string $status,
        public ?CarbonImmutable $paidAt,
        public array $payload,
    ) {}
}
