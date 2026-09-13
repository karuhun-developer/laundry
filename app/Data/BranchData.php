<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreBranchRequest;

final readonly class BranchData
{
    public function __construct(
        public int $organizationId,
        public int $cityId,
        public string $name,
        public string $code,
        public ?string $phone,
        public string $address,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreBranchRequest $request): self
    {
        return new self(
            organizationId: $request->organizationId(),
            cityId: $request->integer('city_id'),
            name: $request->string('name')->toString(),
            code: strtoupper($request->string('code')->toString()),
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            address: $request->string('address')->toString(),
            isActive: $request->boolean('is_active', true),
        );
    }
}
