<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\UpdateOrderTask;
use App\Data\OrderTaskData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderTaskRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;

class OrderTaskController extends Controller
{
    public function __invoke(StoreOrderTaskRequest $request, Organization $organization, Branch $branch, Order $order, UpdateOrderTask $update): OrderResource
    {
        return new OrderResource($update->handle($order, $request->user(), OrderTaskData::fromRequest($request), $request->file('photos', [])));
    }
}
