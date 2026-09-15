<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomerAddresses\SetDefaultCustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetDefaultCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Models\User;
use LogicException;

class SetDefaultCustomerAddressController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        SetDefaultCustomerAddressRequest $request,
        CustomerAddress $address,
        SetDefaultCustomerAddress $setDefaultCustomerAddress,
    ): CustomerAddressResource {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new LogicException('An authenticated customer is required.');
        }

        return new CustomerAddressResource($setDefaultCustomerAddress->handle($customer, $address));
    }
}
