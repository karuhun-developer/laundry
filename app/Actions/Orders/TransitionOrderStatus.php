<?php

namespace App\Actions\Orders;

use App\Data\TransitionOrderStatusData;
use App\Exceptions\InvalidOrderStatusTransition;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class TransitionOrderStatus
{
    public function handle(
        Order $order,
        User $actor,
        TransitionOrderStatusData $data,
    ): Order {
        return DB::transaction(function () use ($order, $actor, $data): Order {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentStatus = $lockedOrder->status;

            if (! $currentStatus->canTransitionTo($data->status)) {
                throw InvalidOrderStatusTransition::between($currentStatus, $data->status);
            }

            $lockedOrder->update(['status' => $data->status]);
            $lockedOrder->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => $data->status,
                'note' => $data->note,
            ]);

            activity('orders')
                ->causedBy($actor)
                ->performedOn($lockedOrder)
                ->withProperties([
                    'from_status' => $currentStatus->value,
                    'to_status' => $data->status->value,
                    'note' => $data->note,
                ])
                ->log('order status transitioned');

            return $lockedOrder->load(['branch', 'items', 'statusEvents']);
        });
    }
}
