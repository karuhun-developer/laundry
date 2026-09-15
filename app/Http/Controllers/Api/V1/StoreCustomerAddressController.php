<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomerAddresses\StoreCustomerAddress;
use App\Data\CustomerAddressData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class StoreCustomerAddressController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreCustomerAddressRequest $request,
        StoreCustomerAddress $storeCustomerAddress,
    ): JsonResponse {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new LogicException('An authenticated customer is required.');
        }

        return (new CustomerAddressResource($storeCustomerAddress->handle(
            $customer,
            CustomerAddressData::fromRequest($request),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
