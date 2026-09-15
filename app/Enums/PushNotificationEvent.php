<?php

namespace App\Enums;

enum PushNotificationEvent
{
    case PickupRequested;
    case PricingConfirmed;
    case PaymentVerified;
    case StatusUpdated;
}
