<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomerAddresses\DeleteCustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteCustomerAddressRequest;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Http\Response;
use LogicException;

class DeleteCustomerAddressController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        DeleteCustomerAddressRequest $request,
        CustomerAddress $address,
        DeleteCustomerAddress $deleteCustomerAddress,
    ): Response {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new LogicException('An authenticated customer is required.');
        }

        $deleteCustomerAddress->handle($customer, $address);

        return response()->noContent();
    }
}
