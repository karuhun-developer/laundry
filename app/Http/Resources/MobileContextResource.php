<?php

namespace App\Http\Resources;

use App\Data\MobileContextData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileContextResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MobileContextData $context */
        $context = $this->resource;

        return [
            'role' => $context->role->value,
            'organization' => [
                'id' => $context->organization->id,
                'name' => $context->organization->name,
                'slug' => $context->organization->slug,
            ],
            'organizations' => $context->organizations->map(
                static fn ($organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ],
            )->values(),
            'branches' => MobileBranchResource::collection($context->branches),
        ];
    }
}
