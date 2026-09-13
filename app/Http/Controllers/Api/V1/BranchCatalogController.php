<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\GetBranchCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchCatalogController extends Controller
{
    public function __invoke(
        Organization $organization,
        Branch $branch,
        GetBranchCatalog $getBranchCatalog,
    ): AnonymousResourceCollection {
        return ServiceResource::collection(
            $getBranchCatalog->handle($organization, $branch),
        );
    }
}
