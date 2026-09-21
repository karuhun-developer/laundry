<?php

namespace Database\Factories;

use App\Enums\CashflowType;
use App\Models\Branch;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashflowEntry> */
class CashflowEntryFactory extends Factory
{
    protected $model = CashflowEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'cashflow_category_id' => CashflowCategory::factory(),
            'type' => fake()->randomElement(CashflowType::cases()),
            'amount_rupiah' => fake()->numberBetween(10000, 5000000),
            'description' => fake()->sentence(),
            'entry_date' => fake()->dateTimeThisYear(),
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
}
