<?php

namespace App\Actions\PriceOptions;

use App\Data\PriceOptionData;
use App\Models\Branch;
use App\Models\PriceOption;

final class UpdatePriceOption
{
    public function handle(PriceOption $priceOption, PriceOptionData $data): PriceOption
    {
        if ($data->branchId !== null) {
            Branch::query()
                ->whereKey($data->branchId)
                ->where('organization_id', $priceOption->service->organization_id)
                ->firstOrFail();
        }

        $priceOption->update([
            'branch_id' => $data->branchId,
            'type' => $data->type,
            'name' => $data->name,
            'package_weight_grams' => $data->packageWeightGrams,
            'base_price_rupiah' => $data->basePriceRupiah,
            'price_per_kilogram_rupiah' => $data->pricePerKilogramRupiah,
            'minimum_chargeable_grams' => $data->minimumChargeableGrams,
            'is_active' => $data->isActive,
        ]);

        return $priceOption->refresh();
    }
}
