<?php

namespace App\Data;

use App\Http\Requests\Cms\StoreServiceRequest;
use App\Http\Requests\Cms\UpdateServiceRequest;

final readonly class ServiceData
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public string $code,
        public ?string $description,
        public bool $isActive,
        public ?int $completionDurationHours = null,
    ) {}

    public static function fromRequest(StoreServiceRequest|UpdateServiceRequest $request): self
    {
        return new self(
            organizationId: $request->organizationId(),
            name: $request->string('name')->toString(),
            code: strtoupper($request->string('code')->toString()),
            description: $request->filled('description')
                ? $request->string('description')->toString()
                : null,
            isActive: $request->boolean('is_active', true),
            completionDurationHours: $request->has('completion_duration_hours')
                ? $request->integer('completion_duration_hours')
                : null,
        );
    }
}
