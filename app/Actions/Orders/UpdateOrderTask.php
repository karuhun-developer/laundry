<?php

namespace App\Actions\Orders;

use App\Actions\PushNotifications\DispatchOrderPushNotification;
use App\Data\OrderTaskData;
use App\Enums\OrderStatus;
use App\Enums\OrderTaskStatus;
use App\Enums\PushNotificationEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateOrderTask
{
    public function __construct(private StoreOrderEvidence $storeEvidence, private DispatchOrderPushNotification $dispatchPush) {}

    /** @param list<UploadedFile> $photos */
    public function handle(Order $order, User $actor, OrderTaskData $data, array $photos = []): Order
    {
        $completedDelivery = false;
        $result = DB::transaction(function () use ($order, $actor, $data, $photos, &$completedDelivery): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $task = $data->taskId === null ? null : $lockedOrder->tasks()->whereKey($data->taskId)->lockForUpdate()->firstOrFail();
            if ($task !== null && $task->kind !== $data->kind) {
                throw ValidationException::withMessages(['kind' => 'Jenis tugas tidak dapat diubah.']);
            }
            if ($task?->status->isTerminal()) {
                if ($task->status === $data->status) {
                    return $lockedOrder->loadWorkspace();
                }
                throw ValidationException::withMessages(['status' => 'Tugas ini sudah ditutup.']);
            }
            if ($lockedOrder->status->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'Pesanan ini sudah ditutup.']);
            }
            if ($task === null) {
                if (! in_array($data->status, [OrderTaskStatus::Waiting, OrderTaskStatus::Scheduled], true)) {
                    throw ValidationException::withMessages(['status' => 'Tugas baru harus menunggu atau dijadwalkan.']);
                }
                if ($lockedOrder->tasks()->where('kind', $data->kind)->whereNotIn('status', [OrderTaskStatus::Completed, OrderTaskStatus::Cancelled])->exists()) {
                    throw ValidationException::withMessages(['task_id' => 'Masih ada tugas aktif untuk jenis ini.']);
                }
                if ($data->kind === 'pickup' && $lockedOrder->priced_at !== null) {
                    throw ValidationException::withMessages(['kind' => 'Pesanan sudah diterima cabang.']);
                }
                $task = $lockedOrder->tasks()->create(['kind' => $data->kind, 'status' => OrderTaskStatus::Waiting]);
            } else {
                $allowed = match ($task->status) {
                    OrderTaskStatus::Waiting => [OrderTaskStatus::Scheduled, OrderTaskStatus::Cancelled],
                    OrderTaskStatus::Scheduled => [OrderTaskStatus::Scheduled, OrderTaskStatus::EnRoute, OrderTaskStatus::Cancelled],
                    OrderTaskStatus::EnRoute => [OrderTaskStatus::Completed, OrderTaskStatus::Failed, OrderTaskStatus::Cancelled],
                    OrderTaskStatus::Failed => [OrderTaskStatus::Scheduled, OrderTaskStatus::Cancelled],
                    OrderTaskStatus::Completed, OrderTaskStatus::Cancelled => [],
                };
                if (! in_array($data->status, $allowed, true)) {
                    throw ValidationException::withMessages(['status' => 'Urutan progres tugas tidak valid.']);
                }
            }
            if ($data->kind === 'delivery' && in_array($data->status, [OrderTaskStatus::EnRoute, OrderTaskStatus::Completed], true)
                && $lockedOrder->status !== OrderStatus::ReadyForPickup) {
                throw ValidationException::withMessages(['status' => 'Laundry harus siap sebelum mulai diantar.']);
            }
            $task->update([
                'status' => $data->status,
                'operator_name' => $data->operatorName ?? $task->operator_name,
                'scheduled_at' => $data->scheduledAt === null ? $task->scheduled_at : Carbon::parse($data->scheduledAt, config('app.timezone'))->setTimezone(config('app.timezone')),
                'address' => $data->address ?? $task->address ?? $lockedOrder->pickup_address_snapshot,
                'recipient_name' => $data->recipientName ?? $task->recipient_name,
                'note' => $data->note,
                'completed_at' => $data->status === OrderTaskStatus::Completed ? now() : null,
            ]);
            $task->events()->create(['order_id' => $lockedOrder->id, 'actor_id' => $actor->id, 'actor_name_snapshot' => $actor->name,
                'status' => $data->status->value, 'note' => $data->note]);
            if ($data->kind === 'delivery' && $data->status === OrderTaskStatus::Completed) {
                $lockedOrder->update(['status' => OrderStatus::Completed]);
                $lockedOrder->statusEvents()->create(['actor_id' => $actor->id, 'actor_name_snapshot' => $actor->name,
                    'status' => OrderStatus::Completed, 'note' => 'Diserahkan kepada '.$data->recipientName.'. '.$data->note]);
                $completedDelivery = true;
            }
            $this->storeEvidence->handle($task, $photos);

            return $lockedOrder->loadWorkspace();
        });
        if ($completedDelivery) {
            $this->dispatchPush->handle($result, PushNotificationEvent::StatusUpdated);
        }

        return $result;
    }
}
