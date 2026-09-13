<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'branch_id' => fn (array $attributes): int => Branch::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'customer_id' => User::factory(),
            'created_by_id' => fn (array $attributes): int => (int) $attributes['customer_id'],
            'order_number' => fake()->unique()->bothify('LDR-########-########'),
            'status' => OrderStatus::Received,
            'customer_name_snapshot' => fake()->name(),
            'customer_whatsapp_phone_snapshot' => null,
            'customer_latitude' => null,
            'customer_longitude' => null,
            'delivery_distance_meters' => null,
            'delivery_free_radius_meters_snapshot' => null,
            'delivery_fee_per_km_rupiah_snapshot' => null,
            'items_subtotal_rupiah' => 12000,
            'delivery_fee_rupiah' => 0,
            'total_rupiah' => 12000,
        ];
    }
}
