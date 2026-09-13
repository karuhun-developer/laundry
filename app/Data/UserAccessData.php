<?php

namespace App\Data;

use App\Enums\RoleName;
use App\Http\Requests\Cms\StoreUserRequest;

final readonly class UserAccessData
{
    /** @param list<int> $branchIds */
    public function __construct(
        public int $organizationId,
        public string $name,
        public string $email,
        public string $password,
        public RoleName $role,
        public array $branchIds,
    ) {}

    public static function fromRequest(StoreUserRequest $request): self
    {
        $branchIds = array_map(
            static fn (mixed $id): int => (int) $id,
            $request->array('branch_ids'),
        );

        return new self(
            organizationId: $request->organizationId(),
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            role: RoleName::from($request->string('role')->toString()),
            branchIds: $branchIds,
        );
    }
}
