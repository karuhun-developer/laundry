<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RoleDestination
{
    public function pathFor(User $user): string
    {
        if ($user->hasAnyRole([RoleName::Customer->value, RoleName::BranchAdmin->value])) {
            return route('mobile.app', absolute: false);
        }

        if ($user->hasAnyRole([RoleName::Owner->value, RoleName::Superadmin->value])) {
            return route('cms.dashboard', absolute: false);
        }

        throw new AuthorizationException;
    }
}
