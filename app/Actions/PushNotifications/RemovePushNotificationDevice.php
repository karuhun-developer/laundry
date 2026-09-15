<?php

namespace App\Actions\PushNotifications;

use App\Models\PushNotificationDevice;
use App\Models\User;

class RemovePushNotificationDevice
{
    public function handle(User $user, string $token): void
    {
        PushNotificationDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->delete();
    }
}
