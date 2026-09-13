<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'city_id' => City::factory(),
            'name' => 'Cabang '.fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(-6.4, -6.0),
            'longitude' => fake()->longitude(106.6, 107.0),
            'free_delivery_radius_meters' => 10000,
            'delivery_fee_per_km_rupiah' => 3000,
            'is_active' => true,
        ];
    }
}
