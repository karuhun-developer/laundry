<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;

final class DeleteOrder
{
    public function handle(Order $order, User $actor): void
    {
        activity('orders')
            ->causedBy($actor)
            ->performedOn($order)
            ->withProperties([
                'order_number' => $order->order_number,
                'branch_id' => $order->branch_id,
            ])
            ->log('order deleted');

        $order->delete();
    }
}
