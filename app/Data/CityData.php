<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreCityRequest;
use App\Http\Requests\Cms\UpdateCityRequest;

final readonly class CityData
{
    public function __construct(
        public string $name,
        public string $province,
    ) {}

    public static function fromRequest(StoreCityRequest|UpdateCityRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            province: $request->string('province')->toString(),
        );
    }
}
