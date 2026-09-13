<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Weighed = 'weighed';
    case Washing = 'washing';
    case Drying = 'drying';
    case Ironing = 'ironing';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
