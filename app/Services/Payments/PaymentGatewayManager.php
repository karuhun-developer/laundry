<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\GatewayPaymentMethodData;
use App\Enums\PaymentGatewayDriver;

final class PaymentGatewayManager
{
    public function __construct(
        private readonly PaywuzPaymentGateway $paywuz,
    ) {}

    public function driver(PaymentGatewayDriver $driver): PaymentGateway
    {
        return match ($driver) {
            PaymentGatewayDriver::Paywuz => $this->paywuz,
        };
    }

    /** @return list<GatewayPaymentMethodData> */
    public function availableMethods(): array
    {
        $methods = [];

        foreach (PaymentGatewayDriver::cases() as $driver) {
            $gateway = $this->driver($driver);

            if ($gateway->isAvailable()) {
                $methods = [...$methods, ...$gateway->methods()];
            }
        }

        usort(
            $methods,
            static fn (GatewayPaymentMethodData $left, GatewayPaymentMethodData $right): int => [$right->isDefault, $left->name] <=> [$left->isDefault, $right->name],
        );

        return $methods;
    }
}
