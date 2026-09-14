<?php

namespace App\Data\Payments;

use App\Enums\PaymentStatus;

final readonly class PaymentGatewayWebhookResultData
{
    public function __construct(
        public bool $duplicate,
        public ?int $paymentId,
        public ?PaymentStatus $status,
    ) {}
}
