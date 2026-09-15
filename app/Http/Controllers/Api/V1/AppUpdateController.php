<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AppUpdates\CheckForAppUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function __invoke(Request $request, CheckForAppUpdate $checkForAppUpdate): JsonResponse
    {
        $validated = $request->validate([
            'current_version' => ['nullable', 'regex:/^[0-9]+\.[0-9]+\.[0-9]+$/'],
        ]);

        return response()->json([
            'data' => $checkForAppUpdate->handle($validated['current_version'] ?? null),
        ]);
    }
}
