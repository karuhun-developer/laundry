<?php

namespace App\Actions\PushNotifications;

use App\Models\PushNotificationDevice;
use App\Models\User;

class RegisterPushNotificationDevice
{
    public function handle(User $user, string $token, string $platform): PushNotificationDevice
    {
        return PushNotificationDevice::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'user_id' => $user->id,
                'token' => $token,
                'platform' => $platform,
                'last_seen_at' => now(),
            ],
        );
    }
}
