<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginPageLogoutResponse implements LogoutResponse
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(null, 204)
            : redirect()->route('login');
    }
}
