<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Mobile\ResolveMobileContext;
use App\Data\MobileContextQueryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobileContextRequest;
use App\Http\Resources\MobileContextResource;
use App\Models\User;
use LogicException;

class MobileContextController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MobileContextRequest $request,
        ResolveMobileContext $resolveMobileContext,
    ): MobileContextResource {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        return new MobileContextResource($resolveMobileContext->handle(
            $user,
            MobileContextQueryData::fromRequest($request),
        ));
    }
}
