<?php

namespace Database\Factories;

use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Models\CashflowCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashflowCategory> */
class CashflowCategoryFactory extends Factory
{
    protected $model = CashflowCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(CashflowType::cases()),
            'group' => fake()->randomElement(CashflowGroup::cases()),
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function debit(): static
    {
        return $this->state(fn (): array => ['type' => CashflowType::Debit]);
    }

    public function credit(): static
    {
        return $this->state(fn (): array => ['type' => CashflowType::Credit]);
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['is_system' => true]);
    }
}
