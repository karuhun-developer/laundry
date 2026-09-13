<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\TransitionPaymentStatus;
use App\Data\TransitionPaymentStatusData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentStatusEventRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use LogicException;

class PaymentStatusEventController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StorePaymentStatusEventRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        Payment $payment,
        TransitionPaymentStatus $transitionPaymentStatus,
    ): PaymentResource {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to update a payment status.');
        }

        return new PaymentResource($transitionPaymentStatus->handle(
            order: $order,
            payment: $payment,
            actor: $actor,
            data: TransitionPaymentStatusData::fromRequest($request),
        ));
    }
}
