<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'order_id' => fn (array $attributes): int => Order::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'branch_id' => $attributes['branch_id'],
            ])->id,
            'recorded_by_id' => User::factory(),
            'payment_number' => fake()->unique()->bothify('PAY-########-########'),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'amount_rupiah' => 12000,
            'qris_payload' => null,
            'gateway_driver' => null,
            'gateway_payment_id' => null,
            'gateway_order_id' => null,
            'gateway_method' => null,
            'gateway_status' => null,
            'gateway_payment_number' => null,
            'gateway_payment_url' => null,
            'gateway_expires_at' => null,
            'gateway_payload' => null,
            'note' => null,
            'settled_at' => now(),
        ];
    }
}
