<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CreateOrder;
use App\Data\CreateOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreOrderRequest $request,
        Organization $organization,
        Branch $branch,
        CreateOrder $createOrder,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to create an order.');
        }

        return (new OrderResource($createOrder->handle(
            organization: $organization,
            branch: $branch,
            actor: $actor,
            data: CreateOrderData::fromRequest($request),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
