<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomerAddresses\ListCustomerAddresses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCustomerAddressesRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use LogicException;

class CustomerAddressIndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ListCustomerAddressesRequest $request,
        ListCustomerAddresses $listCustomerAddresses,
    ): AnonymousResourceCollection {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new LogicException('An authenticated customer is required.');
        }

        return CustomerAddressResource::collection($listCustomerAddresses->handle($customer));
    }
}
