<?php

namespace App\Http\Resources;

use App\Models\OrderComplaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderComplaint */
class OrderComplaintResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'category' => $this->category, 'description' => $this->description,
            'status' => $this->status->value, 'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'history' => $this->events->map(fn ($event): array => ['id' => $event->id, 'status' => $event->status,
                'actor_name' => $event->actor_name_snapshot, 'note' => $event->note, 'occurred_at' => $event->created_at->toIso8601String()]),
            'evidence' => $this->media->map(fn ($media): array => ['id' => $media->id, 'name' => $media->name,
                'url' => route('api.v1.order-evidence.show', $media->id)]),
        ];
    }
}
