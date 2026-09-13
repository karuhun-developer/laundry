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
            'city_id' => fn (array $attributes) => City::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'name' => 'Cabang '.fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
