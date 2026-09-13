<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\ListMobileOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMobileOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use LogicException;

class MobileOrderIndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ListMobileOrdersRequest $request,
        Organization $organization,
        ListMobileOrders $listMobileOrders,
    ): AnonymousResourceCollection {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        return OrderResource::collection($listMobileOrders->handle($organization, $user));
    }
}
