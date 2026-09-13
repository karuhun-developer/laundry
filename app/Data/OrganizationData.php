<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreOrganizationRequest;
use App\Http\Requests\Cms\UpdateOrganizationRequest;

final readonly class OrganizationData
{
    public function __construct(
        public string $name,
        public string $slug,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreOrganizationRequest|UpdateOrganizationRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            slug: $request->string('slug')->toString(),
            isActive: $request->boolean('is_active', true),
        );
    }
}
