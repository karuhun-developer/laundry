<?php

namespace App\Exceptions;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OrderCannotBeCreated extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function branchCoordinatesRequired(): self
    {
        return new self(
            'BRANCH_COORDINATES_REQUIRED',
            'Cabang belum memiliki koordinat untuk menghitung biaya antar jemput.',
            422,
        );
    }

    public static function pickupAlreadyRequested(Order $order): self
    {
        return new self(
            'PICKUP_ALREADY_REQUESTED',
            "Penjemputan untuk pesanan {$order->order_number} masih diproses.",
            409,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
