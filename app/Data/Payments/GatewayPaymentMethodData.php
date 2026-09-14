<?php

namespace App\Data\Payments;

use App\Enums\PaymentGatewayDriver;

final readonly class GatewayPaymentMethodData
{
    public function __construct(
        public PaymentGatewayDriver $driver,
        public string $code,
        public string $name,
        public string $type,
        public int $feeRupiah,
        public int $minimumRupiah,
        public int $maximumRupiah,
        public bool $isDefault,
    ) {}
}
