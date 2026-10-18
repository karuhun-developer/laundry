<?php

namespace App\Data;

use App\Enums\RoleName;
use App\Http\Requests\Cms\UpdateUserRequest;
use App\Services\WhatsAppPhoneNormalizer;

final readonly class UpdateUserAccessData
{
    /** @param list<int> $branchIds */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $whatsappPhone,
        public ?string $password,
        public RoleName $role,
        public array $branchIds,
    ) {}

    public static function fromRequest(UpdateUserRequest $request): self
    {
        $role = RoleName::from($request->string('role')->toString());
        $branchIds = $role === RoleName::BranchAdmin
            ? array_map(
                static fn (mixed $id): int => (int) $id,
                $request->array('branch_ids'),
            )
            : [];

        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            whatsappPhone: app(WhatsAppPhoneNormalizer::class)->normalize(
                $request->filled('whatsapp_phone')
                    ? $request->string('whatsapp_phone')->toString()
                    : null,
            ),
            password: $request->filled('password') ? $request->string('password')->toString() : null,
            role: $role,
            branchIds: $branchIds,
        );
    }
}
