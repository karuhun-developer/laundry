<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PushNotifications\RegisterPushNotificationDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePushNotificationDeviceRequest;
use App\Http\Resources\PushNotificationDeviceResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class StorePushNotificationDeviceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StorePushNotificationDeviceRequest $request,
        RegisterPushNotificationDevice $registerPushNotificationDevice,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required to register a device.');
        }

        return (new PushNotificationDeviceResource($registerPushNotificationDevice->handle(
            user: $user,
            token: $request->string('token')->toString(),
            platform: $request->string('platform')->toString(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
