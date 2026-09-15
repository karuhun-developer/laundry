<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RoleDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    public function __invoke(
        Request $request,
        RoleDestination $roleDestination,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user instanceof User) {
            return redirect($roleDestination->pathFor($user));
        }

        return Inertia::render('Landing');
    }
}
