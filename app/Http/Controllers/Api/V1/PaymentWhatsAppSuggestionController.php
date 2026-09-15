<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\BuildPaymentWhatsAppSuggestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowPaymentWhatsAppSuggestionRequest;
use App\Http\Resources\WhatsAppSuggestionResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;

class PaymentWhatsAppSuggestionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ShowPaymentWhatsAppSuggestionRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        Payment $payment,
        BuildPaymentWhatsAppSuggestion $buildPaymentWhatsAppSuggestion,
    ): WhatsAppSuggestionResource {
        return new WhatsAppSuggestionResource(
            $buildPaymentWhatsAppSuggestion->handle($order, $payment),
        );
    }
}
