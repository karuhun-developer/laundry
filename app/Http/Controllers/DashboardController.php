<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response|RedirectResponse
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        if ($user->hasAnyRole([RoleName::Customer->value, RoleName::BranchAdmin->value])) {
            return to_route('mobile.app');
        }

        return Inertia::render('Dashboard');
    }
}
