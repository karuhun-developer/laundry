<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomerAddresses\UpdateCustomerAddress;
use App\Data\CustomerAddressData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Models\User;
use LogicException;

class UpdateCustomerAddressController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateCustomerAddressRequest $request,
        CustomerAddress $address,
        UpdateCustomerAddress $updateCustomerAddress,
    ): CustomerAddressResource {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new LogicException('An authenticated customer is required.');
        }

        return new CustomerAddressResource($updateCustomerAddress->handle(
            $customer,
            $address,
            CustomerAddressData::fromRequest($request),
        ));
    }
}
