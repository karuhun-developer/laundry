<?php

namespace App\Http\Responses;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\RoleDestination;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomerRegistrationResponse implements RegisterResponse
{
    public function __construct(
        private readonly RoleDestination $roleDestination,
    ) {}

    public function toResponse($request): Response
    {
        if (! $request->user() instanceof User) {
            return redirect()->route('login');
        }

        $user = $request->user();

        if ($user->hasRole(RoleName::Customer->value)) {
            $request->session()->put('customer_session_version', $user->session_version);
        }

        $redirect = $this->roleDestination->pathFor($user);

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect], 201)
            : redirect($redirect);
    }
}
