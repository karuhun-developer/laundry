<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
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
            'branch_id' => null,
            'scope_key' => 'organization',
            'order_status' => fake()->randomElement(OrderStatus::cases()),
            'body' => 'Halo {customer_name}, pesanan {order_number} kini berstatus {status}.',
            'is_active' => true,
        ];
    }

    public function forBranch(?Branch $branch = null): static
    {
        return $this->state(function () use ($branch): array {
            $resolvedBranch = $branch ?? Branch::factory()->create();

            return [
                'organization_id' => $resolvedBranch->organization_id,
                'branch_id' => $resolvedBranch->id,
                'scope_key' => 'branch:'.$resolvedBranch->id,
            ];
        });
    }
}
