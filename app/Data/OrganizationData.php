<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreOrganizationRequest;
use Illuminate\Support\Str;

final readonly class OrganizationData
{
    public function __construct(
        public string $name,
        public string $slug,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreOrganizationRequest $request): self
    {
        $name = $request->string('name')->toString();
        $slug = $request->string('slug')->toString();

        return new self(
            name: $name,
            slug: $slug !== '' ? Str::slug($slug) : Str::slug($name),
            isActive: $request->boolean('is_active', true),
        );
    }
}
