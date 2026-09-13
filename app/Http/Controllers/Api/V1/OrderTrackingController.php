<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;

class OrderTrackingController extends Controller
{
    public function __invoke(
        ShowOrderRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
    ): OrderResource {
        return new OrderResource($order->load(['branch', 'items', 'statusEvents']));
    }
}
