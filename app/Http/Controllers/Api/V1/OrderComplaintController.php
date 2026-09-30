<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\UpdateOrderComplaint;
use App\Data\OrderComplaintData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderComplaintRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;

class OrderComplaintController extends Controller
{
    public function __invoke(StoreOrderComplaintRequest $request, Organization $organization, Branch $branch, Order $order, UpdateOrderComplaint $update): OrderResource
    {
        return new OrderResource($update->handle($order, $request->user(), OrderComplaintData::fromRequest($request), $request->file('photos', [])));
    }
}
