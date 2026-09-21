<?php

namespace App\Data;

use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Http\Requests\Cms\StoreCashflowCategoryRequest;
use App\Http\Requests\Cms\UpdateCashflowCategoryRequest;
use App\Models\CashflowCategory;
use App\Models\User;

final readonly class CashflowCategoryData
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public CashflowType $type,
        public CashflowGroup $group,
    ) {}

    public static function fromStoreRequest(StoreCashflowCategoryRequest $request, User $user): self
    {
        $organizationId = $user->organization_id ?? (int) $request->validated('organization_id');

        return new self(
            organizationId: $organizationId,
            name: trim((string) $request->validated('name')),
            type: CashflowType::from((string) $request->validated('type')),
            group: CashflowGroup::from((string) $request->validated('group')),
        );
    }

    public static function fromUpdateRequest(UpdateCashflowCategoryRequest $request, CashflowCategory $category): self
    {
        return new self(
            organizationId: $category->organization_id,
            name: trim((string) $request->validated('name')),
            type: $category->type,
            group: CashflowGroup::from((string) $request->validated('group')),
        );
    }
}
