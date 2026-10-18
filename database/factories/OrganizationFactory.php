<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_active' => true,
            'qris_enabled' => true,
            'qris_api_key' => 'qris_a00470db65b730b967bb35cf3ae6c93c7eab7e947a078608',
            'qris_base_url' => 'https://qris.karuhundeveloper.com',
        ];
    }
}
