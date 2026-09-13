<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\TransitionOrderStatus;
use App\Data\TransitionOrderStatusData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderStatusEventRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use LogicException;

class OrderStatusEventController extends Controller
{
    public function __invoke(
        StoreOrderStatusEventRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        TransitionOrderStatus $transitionOrderStatus,
    ): OrderResource {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to update an order status.');
        }

        return new OrderResource($transitionOrderStatus->handle(
            order: $order,
            actor: $actor,
            data: TransitionOrderStatusData::fromRequest($request),
        ));
    }
}
