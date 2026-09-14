<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Branches\FindNearestBranches;
use App\Data\NearestBranchQueryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NearestCustomerBranchRequest;
use App\Http\Resources\BranchResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerNearestBranchController extends Controller
{
    public function __invoke(
        NearestCustomerBranchRequest $request,
        FindNearestBranches $findNearestBranches,
    ): AnonymousResourceCollection {
        return BranchResource::collection($findNearestBranches->handle(
            organization: null,
            query: NearestBranchQueryData::fromRequest($request, defaultLimit: null),
        ));
    }
}
