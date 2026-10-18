<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\SettleQrisPaymentAction;
use App\Enums\PaymentStatus;
use App\Exceptions\QrisApiException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Qris\QrisApiClient;
use App\Services\Qris\QrisConfigurationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrisPaymentCheckController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        Payment $payment,
        QrisConfigurationResolver $qrisResolver,
        QrisApiClient $qrisApiClient,
        SettleQrisPaymentAction $settleAction,
    ): JsonResponse {
        if ($payment->status === PaymentStatus::Paid) {
            return response()->json([
                'paid' => true,
                'status' => 'paid',
                'expired' => false,
            ]);
        }

        $isExpired = $payment->qris_expires_at !== null && $payment->qris_expires_at->isPast();

        if (empty($payment->qris_token)) {
            return response()->json([
                'paid' => false,
                'status' => $isExpired ? 'expired' : 'pending',
                'expired' => $isExpired,
            ]);
        }

        $config = $qrisResolver->resolve($branch);
        if ($config === null) {
            return response()->json([
                'paid' => false,
                'status' => $isExpired ? 'expired' : 'pending',
                'expired' => $isExpired,
            ]);
        }

        try {
            $check = $qrisApiClient->check($config, (string) $payment->qris_token);

            if ($check->paid) {
                $settleAction->handle($payment, $check->settledAt, 'Dikonfirmasi via pengecekan kasir');

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
            // Silently fall through
        }

        return response()->json([
            'paid' => false,
            'status' => $isExpired ? 'expired' : 'pending',
            'expired' => $isExpired,
            'expires_at' => $payment->qris_expires_at?->toIso8601String(),
            'remaining_seconds' => ($payment->qris_expires_at !== null && ! $isExpired)
                ? max(0, (int) now()->diffInSeconds($payment->qris_expires_at, false))
                : 0,
        ]);
    }
}
