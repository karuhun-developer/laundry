<?php

namespace App\Http\Resources;

use App\Data\CustomerCredentialData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerCredentialData */
class CustomerCredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'customer' => new CustomerResource($this->customer),
            'credential' => [
                'temporary_pin' => $this->temporaryPin,
                'expires_at' => $this->expiresAt->toIso8601String(),
            ],
        ];
    }
}
