<?php

namespace App\Http\Resources;

use App\Models\OrderTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderTask */
class OrderTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'status' => $this->status->value,
            'operator_name' => $this->operator_name, 'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'address' => $this->address, 'recipient_name' => $this->recipient_name, 'note' => $this->note,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'history' => $this->events->map(fn ($event): array => ['id' => $event->id, 'status' => $event->status,
                'actor_name' => $event->actor_name_snapshot, 'note' => $event->note, 'occurred_at' => $event->created_at->toIso8601String()]),
            'evidence' => $this->media->map(fn ($media): array => ['id' => $media->id, 'name' => $media->name,
                'url' => route('api.v1.order-evidence.show', $media->id)]),
        ];
    }
}
