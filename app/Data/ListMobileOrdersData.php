<?php

namespace App\Data;

use App\Http\Requests\Api\V1\ListMobileOrdersRequest;

final readonly class ListMobileOrdersData
{
    /** @param array<string, mixed> $filters */
    public function __construct(public ?string $search, public array $filters = []) {}

    public static function fromRequest(ListMobileOrdersRequest $request): self
    {
        $search = trim($request->string('search')->toString());

        return new self($search === '' ? null : $search, $request->validated());
    }
}
