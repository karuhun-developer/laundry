<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OrderCannotBeCreated extends RuntimeException
{
    public static function branchCoordinatesRequired(): self
    {
        return new self('Cabang belum memiliki koordinat untuk menghitung biaya antar jemput.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'BRANCH_COORDINATES_REQUIRED',
            'message' => $this->getMessage(),
        ], 422);
    }
}
