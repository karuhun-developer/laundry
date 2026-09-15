<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PushNotifications\RemovePushNotificationDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeletePushNotificationDeviceRequest;
use App\Models\User;
use Illuminate\Http\Response;
use LogicException;

class DeletePushNotificationDeviceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        DeletePushNotificationDeviceRequest $request,
        RemovePushNotificationDevice $removePushNotificationDevice,
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required to remove a device.');
        }

        $removePushNotificationDevice->handle(
            $user,
            $request->string('token')->toString(),
        );

        return response()->noContent();
    }
}
