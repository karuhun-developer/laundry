<?php

namespace App\Exceptions;

use App\Enums\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class InvalidPaymentStatusTransition extends RuntimeException
{
    public static function between(PaymentStatus $from, PaymentStatus $to): self
    {
        return new self("Status pembayaran tidak dapat diubah dari {$from->value} ke {$to->value}.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'INVALID_PAYMENT_STATUS_TRANSITION',
            'message' => $this->getMessage(),
            'errors' => [
                'status' => [$this->getMessage()],
            ],
        ], Response::HTTP_CONFLICT);
    }
}
