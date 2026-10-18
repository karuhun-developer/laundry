<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\RegenerateQrisPaymentAction;
use App\Exceptions\PaymentCannotBeCreated;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Qris\QrisImageGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrisPaymentRegenerateController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        Payment $payment,
        RegenerateQrisPaymentAction $regenerateAction,
        QrisImageGenerator $imageGenerator,
    ): JsonResponse {
        try {
            $updated = $regenerateAction->handle($payment, $request->user()?->name ?? 'Kasir cabang');
        } catch (PaymentCannotBeCreated $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $dataUri = $updated->qris_payload !== null
            ? $imageGenerator->dataUri($updated->qris_payload)
            : null;

        return response()->json([
            'success' => true,
            'qris_payload' => $updated->qris_payload,
            'qris_image_data_uri' => $dataUri,
            'expires_at' => $updated->qris_expires_at?->toIso8601String(),
        ]);
    }
}
