<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customers\UpdateCustomer;
use App\Data\UpdateCustomerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;

class UpdateCustomerController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateCustomerRequest $request,
        User $customer,
        UpdateCustomer $updateCustomer,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated branch admin is required.');
        }

        $organization = $actor->organization()->firstOrFail();
        $updatedCustomer = $updateCustomer->handle(
            organization: $organization,
            actor: $actor,
            customer: $customer,
            data: UpdateCustomerData::fromRequest($request),
        );

        return (new CustomerResource($updatedCustomer))->response();
    }
}
