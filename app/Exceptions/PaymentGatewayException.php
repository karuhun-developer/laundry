<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PaymentGatewayException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $statusCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(string $driver): self
    {
        return new self(
            "Payment gateway {$driver} belum diaktifkan. Gunakan tunai atau QRIS cabang.",
            'PAYMENT_GATEWAY_UNAVAILABLE',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function invalidMethod(string $method): self
    {
        return new self(
            "Metode payment gateway {$method} tidak tersedia.",
            'PAYMENT_GATEWAY_METHOD_INVALID',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function amountOutsideMethodLimits(
        string $method,
        int $minimumRupiah,
        int $maximumRupiah,
    ): self {
        return new self(
            "Nominal {$method} harus antara Rp{$minimumRupiah} dan Rp{$maximumRupiah}.",
            'PAYMENT_GATEWAY_AMOUNT_INVALID',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function providerFailure(string $driver, Throwable $previous): self
    {
        return new self(
            "Layanan pembayaran {$driver} sedang tidak dapat dihubungi.",
            'PAYMENT_GATEWAY_PROVIDER_ERROR',
            Response::HTTP_BAD_GATEWAY,
            $previous,
        );
    }

    public static function invalidProviderResponse(string $driver): self
    {
        return new self(
            "Respons layanan pembayaran {$driver} tidak valid.",
            'PAYMENT_GATEWAY_PROVIDER_ERROR',
            Response::HTTP_BAD_GATEWAY,
        );
    }

    public static function invalidWebhook(string $message = 'Tanda tangan webhook tidak valid.'): self
    {
        return new self(
            $message,
            'PAYMENT_GATEWAY_WEBHOOK_INVALID',
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function webhookPaymentNotFound(): self
    {
        return new self(
            'Pembayaran untuk webhook tidak ditemukan.',
            'PAYMENT_GATEWAY_PAYMENT_NOT_FOUND',
            Response::HTTP_NOT_FOUND,
        );
    }

    public static function webhookDataMismatch(string $message): self
    {
        return new self(
            $message,
            'PAYMENT_GATEWAY_WEBHOOK_MISMATCH',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
