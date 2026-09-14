<?php

namespace App\Http\Controllers;

use App\Actions\Auth\AuthenticateCustomer;
use App\Http\Requests\CustomerLoginRequest;
use App\Support\RoleDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CustomerLoginController extends Controller
{
    public function __invoke(
        CustomerLoginRequest $request,
        AuthenticateCustomer $authenticateCustomer,
        RoleDestination $roleDestination,
    ): RedirectResponse {
        $customer = $authenticateCustomer->handle(
            whatsappPhone: $request->string('whatsapp_phone')->toString(),
            pin: $request->string('pin')->toString(),
            throttleKey: $request->throttleKey(),
        );

        Auth::login($customer, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('customer_session_version', $customer->session_version);

        return redirect($roleDestination->pathFor($customer));
    }
}
