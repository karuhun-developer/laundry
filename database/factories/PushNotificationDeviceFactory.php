<?php

namespace Database\Factories;

use App\Models\PushNotificationDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotificationDevice>
 */
class PushNotificationDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = fake()->unique()->sha256();

        return [
            'user_id' => User::factory(),
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
        ];
    }
}
