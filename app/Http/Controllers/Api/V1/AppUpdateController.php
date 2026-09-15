<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function __invoke(Request $request, AppUpdateService $appUpdates): JsonResponse
    {
        $validated = $request->validate([
            'current_version' => ['nullable', 'regex:/^[0-9]+\.[0-9]+\.[0-9]+$/'],
        ]);

        return response()->json([
            'data' => $appUpdates->check($validated['current_version'] ?? null),
        ]);
    }
}
