<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class PaymentCannotBeCreated extends RuntimeException
{
    public static function pricingNotConfirmed(): self
    {
        return new self('Harga pesanan belum dikonfirmasi oleh petugas.');
    }

    public static function orderAlreadyPaid(): self
    {
        return new self('Pesanan ini sudah lunas.');
    }

    public static function amountExceedsOutstanding(int $outstandingRupiah): self
    {
        return new self("Nominal melebihi sisa tagihan Rp{$outstandingRupiah}.");
    }

    public static function pendingPaymentExists(): self
    {
        return new self('Selesaikan atau batalkan pembayaran tertunda sebelumnya.');
    }

    public static function qrisUnavailable(): self
    {
        return new self('QRIS belum dikonfigurasi untuk cabang ini.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'PAYMENT_CANNOT_BE_CREATED',
            'message' => $this->getMessage(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
