<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\RegenerateQrisPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentCannotBeCreated;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Qris\QrisImageGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PublicOrderPaymentRegenerateController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        RegenerateQrisPaymentAction $regenerateAction,
        QrisImageGenerator $imageGenerator,
    ): JsonResponse|RedirectResponse {
        /** @var Payment|null $pendingPayment */
        $pendingPayment = $order->payments()
            ->where('method', PaymentMethod::Qris)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pendingPayment === null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada pembayaran QRIS pending yang dapat digenerate ulang.',
                ], 422);
            }

            return redirect()->to(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))
                ->with('error', 'Tidak ada pembayaran QRIS pending yang dapat digenerate ulang.');
        }

        try {
            $updatedPayment = $regenerateAction->handle($pendingPayment, 'Pelanggan');
        } catch (PaymentCannotBeCreated $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->to(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))
                ->with('error', $e->getMessage());
        }

        $dataUri = $updatedPayment->qris_payload !== null
            ? $imageGenerator->dataUri($updatedPayment->qris_payload)
            : null;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'qris_payload' => $updatedPayment->qris_payload,
                'qris_image_data_uri' => $dataUri,
                'expires_at' => $updatedPayment->qris_expires_at?->toIso8601String(),
                'remaining_seconds' => $updatedPayment->qris_expires_at !== null
                    ? max(0, (int) now()->diffInSeconds($updatedPayment->qris_expires_at, false))
                    : 900,
            ]);
        }

        return redirect()->to(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))
            ->with('status', 'QRIS berhasil dibuat ulang. Silakan selesaikan pembayaran.');
    }
}
