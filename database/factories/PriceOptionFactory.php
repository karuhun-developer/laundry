<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Models\Branch;
use App\Models\PriceOption;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceOption>
 */
class PriceOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'branch_id' => function (array $attributes): int {
                $service = Service::query()->findOrFail($attributes['service_id']);

                return Branch::factory()->for($service->organization)->create()->id;
            },
            'type' => PriceType::PerKilogram,
            'name' => 'Kiloan reguler',
            'package_weight_grams' => null,
            'base_price_rupiah' => null,
            'price_per_kilogram_rupiah' => 12000,
            'minimum_chargeable_grams' => 1000,
            'is_active' => true,
        ];
    }

    public function fixedPackage(): static
    {
        return $this->state(fn (): array => [
            'type' => PriceType::FixedPackage,
            'name' => 'Paket 12 kg',
            'package_weight_grams' => 12000,
            'base_price_rupiah' => 120000,
            'price_per_kilogram_rupiah' => 12000,
            'minimum_chargeable_grams' => null,
        ]);
    }

    public function forAllBranches(): static
    {
        return $this->state(fn (): array => [
            'branch_id' => null,
        ]);
    }
}
