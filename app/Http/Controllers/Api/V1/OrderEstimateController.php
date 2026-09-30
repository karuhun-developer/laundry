<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\OverrideOrderEstimate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderEstimateRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;

class OrderEstimateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreOrderEstimateRequest $request, Organization $organization, Branch $branch, Order $order, OverrideOrderEstimate $override): OrderResource
    {
        return new OrderResource($override->handle($order, $request->user(), $request->string('estimated_ready_at')->toString(), $request->string('reason')->toString()));
    }
}
