<?php

namespace App\Actions\PushNotifications;

use App\Enums\PushNotificationEvent;
use App\Jobs\SendOrderPushNotification;
use App\Models\Order;

class DispatchOrderPushNotification
{
    public function handle(Order $order, PushNotificationEvent $event): void
    {
        SendOrderPushNotification::dispatch($order->id, $event)->afterCommit();
    }
}
