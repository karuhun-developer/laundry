<?php

namespace App\Data;

use App\Http\Requests\Api\V1\ListMobileOrdersRequest;

final readonly class ListMobileOrdersData
{
    public function __construct(public ?string $search) {}

    public static function fromRequest(ListMobileOrdersRequest $request): self
    {
        $search = trim($request->string('search')->toString());

        return new self($search === '' ? null : $search);
    }
}
