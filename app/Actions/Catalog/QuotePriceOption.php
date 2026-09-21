<?php

namespace App\Actions\Catalog;

use App\Data\PriceQuoteData;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Services\PriceCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class QuotePriceOption
{
    public function __construct(private PriceCalculator $priceCalculator) {}

    public function handle(
        Organization $organization,
        Branch $branch,
        PriceOption $priceOption,
        int $weightGrams,
        ?int $customPricePerKilogramRupiah = null,
    ): PriceQuoteData {
        if (! $organization->is_active
            || ! $branch->is_active
            || $branch->organization_id !== $organization->id) {
            throw (new ModelNotFoundException)->setModel(Branch::class, [$branch->id]);
        }

        $availablePriceOption = PriceOption::query()
            ->with('service')
            ->whereKey($priceOption->id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branch->id))
            ->where('is_active', true)
            ->whereHas('service', fn (Builder $query): Builder => $query
                ->where('organization_id', $organization->id)
                ->where('is_active', true))
            ->firstOrFail();

        return $this->priceCalculator->quote(
            $availablePriceOption,
            $weightGrams,
            $customPricePerKilogramRupiah,
        );
    }
}
