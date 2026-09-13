<?php

namespace App\Enums;

enum PriceType: string
{
    case FixedPackage = 'fixed_package';
    case PerKilogram = 'per_kilogram';
}
