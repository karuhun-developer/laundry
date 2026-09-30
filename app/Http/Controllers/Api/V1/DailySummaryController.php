<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\BuildDailySummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DailySummaryRequest;
use Illuminate\Http\JsonResponse;

class DailySummaryController extends Controller
{
    public function __invoke(DailySummaryRequest $request, BuildDailySummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->handle($request->user(), $request->validated())]);
    }
}
