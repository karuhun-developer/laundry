<?php

namespace App\Data\Payments;

final readonly class CreateGatewayPaymentData
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $orderId,
        public int $amountRupiah,
        public string $paymentMethod,
        public array $metadata = [],
    ) {}
}
