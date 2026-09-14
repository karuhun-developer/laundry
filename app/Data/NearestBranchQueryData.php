<?php

namespace App\Data;

use App\Http\Requests\Api\V1\NearestBranchRequest;

final readonly class NearestBranchQueryData
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?int $limit,
    ) {}

    public static function fromRequest(NearestBranchRequest $request, ?int $defaultLimit = 5): self
    {
        return new self(
            latitude: $request->float('latitude'),
            longitude: $request->float('longitude'),
            limit: $request->filled('limit') ? $request->integer('limit') : $defaultLimit,
        );
    }
}
