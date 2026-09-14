<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customers\DetachCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DetachCustomerRequest;
use App\Models\User;
use Illuminate\Http\Response;
use LogicException;

class DetachCustomerController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        DetachCustomerRequest $request,
        User $customer,
        DetachCustomer $detachCustomer,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated branch admin is required.');
        }

        $organization = $actor->organization()->firstOrFail();
        $detachCustomer->handle($organization, $actor, $customer);

        return response()->noContent();
    }
}
