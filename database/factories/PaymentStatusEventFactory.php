<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentStatusEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentStatusEvent>
 */
class PaymentStatusEventFactory extends Factory
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
            'actor_id' => User::factory(),
            'actor_name_snapshot' => fake()->name(),
            'status' => PaymentStatus::Paid,
            'note' => null,
        ];
    }
}
