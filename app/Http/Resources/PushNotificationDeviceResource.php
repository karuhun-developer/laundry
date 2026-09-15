<?php

namespace App\Http\Resources;

use App\Models\PushNotificationDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PushNotificationDevice */
class PushNotificationDeviceResource extends JsonResource
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
            'platform' => $this->platform,
            'last_seen_at' => $this->last_seen_at->toISOString(),
        ];
    }
}
