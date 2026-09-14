<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RoleDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleRedirectController extends Controller
{
    public function __invoke(Request $request, RoleDestination $roleDestination): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return to_route('login');
        }

        return redirect($roleDestination->pathFor($user));
    }
}
