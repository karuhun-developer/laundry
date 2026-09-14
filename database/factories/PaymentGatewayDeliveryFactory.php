<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayDriver;
use App\Models\Payment;
use App\Models\PaymentGatewayDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGatewayDelivery>
 */
class PaymentGatewayDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'gateway_driver' => PaymentGatewayDriver::Paywuz,
            'delivery_id' => fake()->unique()->uuid(),
            'event' => 'transaction.paid',
            'payload' => [
                'event' => 'transaction.paid',
                'data' => [],
            ],
            'processed_at' => now(),
        ];
    }
}
