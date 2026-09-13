<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowOrderReceiptRequest;
use App\Http\Resources\OrderReceiptResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;

class OrderReceiptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ShowOrderReceiptRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
    ): OrderReceiptResource {
        return new OrderReceiptResource($order->load([
            'branch.city',
            'items',
            'payments.statusEvents',
            'statusEvents',
        ]));
    }
}
