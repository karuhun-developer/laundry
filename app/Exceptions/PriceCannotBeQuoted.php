<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PriceCannotBeQuoted extends RuntimeException
{
    public function __construct(string $message, private string $field = 'weight_grams')
    {
        parent::__construct($message);
    }

    public static function missingPackageExcessRate(): self
    {
        return new self('Berat melebihi batas paket dan tarif kelebihan belum diatur.', 'weight_grams');
    }

    public static function belowFloorPrice(int $floorPrice): self
    {
        return new self(
            'Harga per kilogram tidak boleh lebih rendah dari harga bawah (Rp '.number_format($floorPrice, 0, ',', '.').').',
            'custom_price_per_kilogram_rupiah',
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->field === 'weight_grams' ? 'PACKAGE_EXCESS_RATE_REQUIRED' : 'PRICE_CANNOT_BE_QUOTED',
            'message' => $this->getMessage(),
            'errors' => [
                $this->field => [$this->getMessage()],
            ],
        ], 422);
    }
}
