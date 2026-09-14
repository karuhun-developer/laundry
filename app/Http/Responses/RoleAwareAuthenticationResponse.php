<?php

namespace App\Http\Responses;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\RoleDestination;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleAwareAuthenticationResponse implements LoginResponse, PasskeyLoginResponse, TwoFactorLoginResponse
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
        $redirect = $this->roleDestination->pathFor($user);

        if ($user->hasRole(RoleName::Customer->value)) {
            $request->session()->put('customer_session_version', $user->session_version);
        }

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect, 'two_factor' => false])
            : redirect($redirect);
    }
}
