<?php

namespace App\Data;

use App\Http\Requests\Api\V1\MobileContextRequest;

final readonly class MobileContextQueryData
{
    public function __construct(public ?string $organizationSlug) {}

    public static function fromRequest(MobileContextRequest $request): self
    {
        return new self($request->validated('organization'));
    }
}
