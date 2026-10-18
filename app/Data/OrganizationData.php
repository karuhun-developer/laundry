<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreOrganizationRequest;
use App\Http\Requests\Cms\UpdateOrganizationRequest;

final readonly class OrganizationData
{
    public function __construct(
        public string $name,
        public bool $isActive,
        public bool $qrisEnabled = true,
        public ?string $qrisApiKey = null,
        public ?string $qrisBaseUrl = null,
    ) {}

    public static function fromRequest(StoreOrganizationRequest|UpdateOrganizationRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            isActive: $request->boolean('is_active', true),
            qrisEnabled: $request->boolean('qris_enabled', true),
            qrisApiKey: $request->filled('qris_api_key') ? $request->string('qris_api_key')->toString() : null,
            qrisBaseUrl: $request->filled('qris_base_url') ? $request->string('qris_base_url')->toString() : null,
        );
    }
}
