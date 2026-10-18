<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\SettleQrisPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\QrisApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Qris\QrisApiClient;
use App\Services\Qris\QrisConfigurationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOrderPaymentCheckController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        QrisConfigurationResolver $qrisResolver,
        QrisApiClient $qrisApiClient,
        SettleQrisPaymentAction $settleAction,
    ): JsonResponse {
        /** @var Payment|null $pendingPayment */
        $pendingPayment = $order->payments()
            ->where('method', PaymentMethod::Qris)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        // If no pending QRIS payment, check if any payment has already been settled as Paid
        if ($pendingPayment === null) {
            $isPaid = $order->payments()
                ->where('status', PaymentStatus::Paid)
                ->exists();

            return response()->json([
                'paid' => $isPaid,
                'status' => $isPaid ? 'paid' : 'none',
                'expired' => false,
            ]);
        }

        // Check if QRIS has already expired (15 minutes limit)
        $isExpired = $pendingPayment->qris_expires_at !== null && $pendingPayment->qris_expires_at->isPast();

        if (empty($pendingPayment->qris_token)) {
            return response()->json([
                'paid' => false,
                'status' => $isExpired ? 'expired' : 'pending',
                'expired' => $isExpired,
            ]);
        }

        $config = $qrisResolver->resolve($order->branch);
        if ($config === null) {
            return response()->json([
                'paid' => false,
                'status' => $isExpired ? 'expired' : 'pending',
                'expired' => $isExpired,
            ]);
        }

        try {
            $check = $qrisApiClient->check($config, (string) $pendingPayment->qris_token);

            if ($check->paid) {
                $settleAction->handle($pendingPayment, $check->settledAt);

                return response()->json([
                    'paid' => true,
                    'status' => 'paid',
                    'expired' => false,
                ]);
            }

            if ($check->status === 'expired') {
                return response()->json([
                    'paid' => false,
                    'status' => 'expired',
                    'expired' => true,
                ]);
            }
        } catch (QrisApiException) {
            // In case of transient network error, fall through to current local state
        }

        return response()->json([
            'paid' => false,
            'status' => $isExpired ? 'expired' : 'pending',
            'expired' => $isExpired,
            'expires_at' => $pendingPayment->qris_expires_at?->toIso8601String(),
            'remaining_seconds' => ($pendingPayment->qris_expires_at !== null && ! $isExpired)
                ? max(0, (int) now()->diffInSeconds($pendingPayment->qris_expires_at, false))
                : 0,
        ]);
    }
}
