<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\ListMobileOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCustomerOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use LogicException;

class CustomerOrderIndexController extends Controller
{
    public function __invoke(
        ListCustomerOrdersRequest $request,
        ListMobileOrders $listMobileOrders,
    ): AnonymousResourceCollection {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        return OrderResource::collection($listMobileOrders->handleForCustomer(
            $user,
            $request->validated(),
        ));
    }
}
