<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\CreatePayment;
use App\Data\CreatePaymentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StorePaymentRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        CreatePayment $createPayment,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to create a payment.');
        }

        return (new PaymentResource($createPayment->handle(
            order: $order,
            branch: $branch,
            actor: $actor,
            data: CreatePaymentData::fromRequest($request),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
