<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PriceCannotBeQuoted extends RuntimeException
{
    public static function missingPackageExcessRate(): self
    {
        return new self('Berat melebihi batas paket dan tarif kelebihan belum diatur.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'PACKAGE_EXCESS_RATE_REQUIRED',
            'message' => $this->getMessage(),
        ], 422);
    }
}
