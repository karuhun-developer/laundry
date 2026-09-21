<?php

namespace App\Actions\PriceOptions;

use App\Data\PriceOptionData;
use App\Models\Branch;
use App\Models\PriceOption;
use App\Models\Service;

final class CreatePriceOption
{
    public function handle(Service $service, PriceOptionData $data): PriceOption
    {
        if ($data->branchId !== null) {
            Branch::query()
                ->whereKey($data->branchId)
                ->where('organization_id', $service->organization_id)
                ->firstOrFail();
        }

        return $service->priceOptions()->create([
            'branch_id' => $data->branchId,
            'type' => $data->type,
            'name' => $data->name,
            'package_weight_grams' => $data->packageWeightGrams,
            'base_price_rupiah' => $data->basePriceRupiah,
            'price_per_kilogram_rupiah' => $data->pricePerKilogramRupiah,
            'min_price_per_kilogram_rupiah' => $data->minPricePerKilogramRupiah,
            'minimum_chargeable_grams' => $data->minimumChargeableGrams,
            'is_active' => $data->isActive,
        ]);
    }
}
