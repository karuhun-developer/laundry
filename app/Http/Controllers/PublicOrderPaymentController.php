<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SelectPublicOrderPayment;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentCannotBeCreated;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class PublicOrderPaymentController extends Controller
{
    public function __invoke(Request $request, Order $order, SelectPublicOrderPayment $selectPayment): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'gateway_driver' => ['nullable', Rule::enum(PaymentGatewayDriver::class)],
            'gateway_method' => ['required_if:method,gateway', 'nullable', 'string', 'max:40', Rule::notIn(['VA', 'ALL'])],
        ]);
        $trackingUrl = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);
        $method = PaymentMethod::from((string) $validated['method']);
        $gatewayDriver = $method === PaymentMethod::Gateway
            ? PaymentGatewayDriver::from($validated['gateway_driver'] ?? PaymentGatewayDriver::Paywuz->value)
            : null;

        try {
            $selectPayment->handle(
                order: $order,
                method: $method,
                gatewayDriver: $gatewayDriver,
                gatewayMethod: $method === PaymentMethod::Gateway ? $validated['gateway_method'] : null,
            );
        } catch (PaymentCannotBeCreated|PaymentGatewayException $exception) {
            if ($exception instanceof PaymentGatewayException) {
                report($exception);
            }

            return redirect()->to($trackingUrl)->with('error', $exception->getMessage());
        }

        return redirect()->to($trackingUrl);
    }
}
