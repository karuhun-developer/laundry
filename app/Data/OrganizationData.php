<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreOrganizationRequest;
use App\Http\Requests\Cms\UpdateOrganizationRequest;

final readonly class OrganizationData
{
    public function __construct(
        public string $name,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreOrganizationRequest|UpdateOrganizationRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            isActive: $request->boolean('is_active', true),
        );
    }
}
