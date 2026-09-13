<?php

namespace App\Http\Resources;

use App\Models\PaymentStatusEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentStatusEvent */
class PaymentStatusEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'actor' => [
                'id' => $this->actor_id,
                'name' => $this->actor_name_snapshot,
            ],
            'note' => $this->note,
            'occurred_at' => $this->created_at->toISOString(),
        ];
    }
}
