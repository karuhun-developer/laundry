<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OverrideOrderEstimate
{
    public function handle(Order $order, User $actor, string $estimatedReadyAt, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $estimatedReadyAt, $reason): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->priced_at === null || $lockedOrder->status->isTerminal()) {
                throw ValidationException::withMessages(['estimated_ready_at' => 'Estimasi hanya dapat diubah untuk pesanan aktif yang sudah ditimbang.']);
            }
            $previous = $lockedOrder->estimated_ready_at?->toIso8601String();
            $lockedOrder->update(['estimated_ready_at' => Carbon::parse($estimatedReadyAt, config('app.timezone'))->setTimezone(config('app.timezone'))]);
            $lockedOrder->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => $lockedOrder->status,
                'note' => 'Estimasi diubah: '.$previous.' → '.$lockedOrder->estimated_ready_at->toIso8601String().'. '.$reason,
            ]);

            return $lockedOrder->loadWorkspace();
        });
    }
}
