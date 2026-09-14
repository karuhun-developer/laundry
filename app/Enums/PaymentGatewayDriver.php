<?php

namespace App\Enums;

enum PaymentGatewayDriver: string
{
    case Paywuz = 'paywuz';

    public function label(): string
    {
        return match ($this) {
            self::Paywuz => 'Paywuz',
        };
    }
}
