<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreCityRequest;

final readonly class CityData
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public string $province,
    ) {}

    public static function fromRequest(StoreCityRequest $request): self
    {
        return new self(
            organizationId: $request->organizationId(),
            name: $request->string('name')->toString(),
            province: $request->string('province')->toString(),
        );
    }
}
