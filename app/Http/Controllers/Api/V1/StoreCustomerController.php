<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customers\CreateOrAttachCustomer;
use App\Data\StoreCustomerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Resources\CustomerCredentialResource;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class StoreCustomerController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreCustomerRequest $request,
        CreateOrAttachCustomer $createOrAttachCustomer,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated branch admin is required.');
        }

        $organization = $actor->organization()->firstOrFail();
        $result = $createOrAttachCustomer->handle(
            organization: $organization,
            actor: $actor,
            data: StoreCustomerData::fromRequest($request),
        );

        if ($result->credential !== null) {
            return (new CustomerCredentialResource($result->credential))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        return (new CustomerResource($result->customer))->response();
    }
}
