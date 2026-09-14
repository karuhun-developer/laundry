<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\HandlePaymentGatewayWebhook;
use App\Data\Payments\PaymentGatewayWebhookData;
use App\Enums\PaymentGatewayDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentGatewayWebhookRequest;
use Illuminate\Http\JsonResponse;

class PaymentGatewayWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StorePaymentGatewayWebhookRequest $request,
        HandlePaymentGatewayWebhook $handlePaymentGatewayWebhook,
    ): JsonResponse {
        $result = $handlePaymentGatewayWebhook->handle(
            PaymentGatewayDriver::Paywuz,
            PaymentGatewayWebhookData::fromRequest($request),
        );

        return response()->json([
            'received' => true,
            'duplicate' => $result->duplicate,
            'payment_id' => $result->paymentId,
            'status' => $result->status?->value,
        ]);
    }
}
