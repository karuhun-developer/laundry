<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\UpdateCustomerPin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCustomerPinRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;

class UpdateCustomerPinController extends Controller
{
    public function __invoke(
        UpdateCustomerPinRequest $request,
        UpdateCustomerPin $updateCustomerPin,
    ): JsonResponse {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new LogicException('An authenticated customer is required.');
        }

        $customer = $updateCustomerPin->handle(
            customer: $customer,
            currentPin: $request->string('current_pin')->toString(),
            pin: $request->string('pin')->toString(),
            currentSessionId: $request->hasSession() ? $request->session()->getId() : null,
        );

        if ($request->hasSession()) {
            $request->session()->put('customer_session_version', $customer->session_version);
        }

        return response()->json([
            'message' => 'PIN berhasil diperbarui.',
            'data' => [
                'must_change_pin' => false,
                'pin_expires_at' => null,
            ],
        ]);
    }
}
