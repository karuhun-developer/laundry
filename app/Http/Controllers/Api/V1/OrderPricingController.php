<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\ConfirmOrderPricing;
use App\Data\ConfirmOrderPricingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderPricingRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use LogicException;

class OrderPricingController extends Controller
{
    public function __invoke(
        StoreOrderPricingRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        ConfirmOrderPricing $confirmOrderPricing,
    ): OrderResource {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to confirm order pricing.');
        }

        return new OrderResource($confirmOrderPricing->handle(
            organization: $organization,
            branch: $branch,
            order: $order,
            actor: $actor,
            data: ConfirmOrderPricingData::fromRequest($request),
        ));
    }
}
