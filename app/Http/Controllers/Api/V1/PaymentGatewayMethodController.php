<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPaymentGatewayMethodsRequest;
use App\Http\Resources\PaymentGatewayMethodResource;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentGatewayMethodController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ListPaymentGatewayMethodsRequest $request,
        PaymentGatewayManager $paymentGatewayManager,
    ): AnonymousResourceCollection {
        return PaymentGatewayMethodResource::collection(
            $paymentGatewayManager->availableMethods(),
        );
    }
}
