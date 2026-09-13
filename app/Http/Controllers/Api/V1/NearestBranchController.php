<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Branches\FindNearestBranches;
use App\Data\NearestBranchQueryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NearestBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NearestBranchController extends Controller
{
    public function __invoke(
        NearestBranchRequest $request,
        Organization $organization,
        FindNearestBranches $findNearestBranches,
    ): AnonymousResourceCollection {
        $branches = $findNearestBranches->handle(
            $organization,
            NearestBranchQueryData::fromRequest($request),
        );

        return BranchResource::collection($branches);
    }
}
