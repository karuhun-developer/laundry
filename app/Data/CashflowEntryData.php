<?php

namespace App\Data;

use App\Enums\CashflowType;
use App\Http\Requests\Cms\StoreCashflowEntryRequest;
use Illuminate\Support\Carbon;

final readonly class CashflowEntryData
{
    public function __construct(
        public int $organizationId,
        public int $branchId,
        public int $cashflowCategoryId,
        public CashflowType $type,
        public int $amountRupiah,
        public string $description,
        public Carbon $entryDate,
    ) {}

    public static function fromRequest(StoreCashflowEntryRequest $request): self
    {
        return new self(
            organizationId: (int) $request->validated('organization_id'),
            branchId: (int) $request->validated('branch_id'),
            cashflowCategoryId: (int) $request->validated('cashflow_category_id'),
            type: CashflowType::from($request->validated('type')),
            amountRupiah: (int) $request->validated('amount_rupiah'),
            description: $request->validated('description'),
            entryDate: Carbon::parse($request->validated('entry_date')),
        );
    }
}
