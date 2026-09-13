<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\ListOrganizationCustomers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListOrganizationCustomersRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerIndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ListOrganizationCustomersRequest $request,
        Organization $organization,
        ListOrganizationCustomers $listCustomers,
    ): AnonymousResourceCollection {
        return CustomerResource::collection($listCustomers->handle($organization));
    }
}
