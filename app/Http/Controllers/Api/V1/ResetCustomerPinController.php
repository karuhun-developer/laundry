<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customers\ResetCustomerPin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResetCustomerPinRequest;
use App\Http\Resources\CustomerCredentialResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;

class ResetCustomerPinController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ResetCustomerPinRequest $request,
        User $customer,
        ResetCustomerPin $resetCustomerPin,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated branch admin is required.');
        }

        $organization = $actor->organization()->firstOrFail();
        $credential = $resetCustomerPin->handle($organization, $actor, $customer);

        return (new CustomerCredentialResource($credential))->response();
    }
}
