<?php

namespace App\Actions\Orders;

use App\Data\OrderComplaintData;
use App\Enums\OrderComplaintStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateOrderComplaint
{
    public function __construct(private StoreOrderEvidence $storeEvidence) {}

    /** @param list<UploadedFile> $photos */
    public function handle(Order $order, User $actor, OrderComplaintData $data, array $photos = []): Order
    {
        return DB::transaction(function () use ($order, $actor, $data, $photos): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($data->complaintId === null) {
                if ($data->status !== OrderComplaintStatus::New || $lockedOrder->complaints()->where('status', '!=', OrderComplaintStatus::Resolved)->exists()) {
                    throw ValidationException::withMessages(['complaint_id' => 'Selesaikan komplain aktif sebelum membuat komplain baru.']);
                }
                $complaint = $lockedOrder->complaints()->create(['created_by_id' => $actor->id, 'category' => $data->category,
                    'description' => $data->description, 'status' => OrderComplaintStatus::New]);
            } else {
                $complaint = $lockedOrder->complaints()->whereKey($data->complaintId)->lockForUpdate()->firstOrFail();
                if ($complaint->status === OrderComplaintStatus::Resolved || $data->status === OrderComplaintStatus::New
                    || ($complaint->status === OrderComplaintStatus::New && $data->status === OrderComplaintStatus::Resolved)) {
                    throw ValidationException::withMessages(['status' => 'Komplain harus ditangani dahulu dan tidak dapat dibuka ulang.']);
                }
                $complaint->update(['status' => $data->status,
                    'resolution_note' => $data->status === OrderComplaintStatus::Resolved ? $data->note : null,
                    'resolved_at' => $data->status === OrderComplaintStatus::Resolved ? now() : null]);
            }
            $complaint->events()->create(['order_id' => $lockedOrder->id, 'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name, 'status' => $data->status->value, 'note' => $data->note ?? $data->description]);
            $this->storeEvidence->handle($complaint, $photos);

            return $lockedOrder->loadWorkspace();
        });
    }
}
