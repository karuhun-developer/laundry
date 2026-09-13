<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class InvalidOrderStatusTransition extends RuntimeException
{
    private function __construct(
        public readonly OrderStatus $currentStatus,
        public readonly OrderStatus $requestedStatus,
    ) {
        parent::__construct(sprintf(
            'Status pesanan tidak dapat diubah dari %s menjadi %s.',
            $currentStatus->value,
            $requestedStatus->value,
        ));
    }

    public static function between(OrderStatus $currentStatus, OrderStatus $requestedStatus): self
    {
        return new self($currentStatus, $requestedStatus);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'INVALID_ORDER_STATUS_TRANSITION',
            'message' => $this->getMessage(),
            'errors' => [
                'status' => [$this->getMessage()],
            ],
        ], Response::HTTP_CONFLICT);
    }
}
