<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class OrderPricingCannotBeConfirmed extends RuntimeException
{
    public static function invalidState(): self
    {
        return new self('Harga hanya dapat dikonfirmasi untuk pesanan yang menunggu dijemput.');
    }

    public static function priceOptionDoesNotMatch(): self
    {
        return new self('Pilihan layanan tidak sesuai dengan layanan yang dipilih pelanggan.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'ORDER_PRICING_CANNOT_BE_CONFIRMED',
            'message' => $this->getMessage(),
        ], Response::HTTP_CONFLICT);
    }
}
