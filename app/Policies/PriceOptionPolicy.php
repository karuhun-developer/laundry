<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\PriceOption;
use App\Models\Service;
use App\Models\User;

class PriceOptionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageCatalog->value)
            && ($user->hasRole(RoleName::Superadmin->value) || $user->organization_id !== null);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PriceOption $priceOption): bool
    {
        return $user->can(PermissionName::ManageCatalog->value)
            && ($user->hasRole(RoleName::Superadmin->value)
                || ($user->organization_id !== null
                    && $user->organization_id === $priceOption->service->organization_id));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Service $service): bool
    {
        return $user->can(PermissionName::ManageCatalog->value)
            && ($user->hasRole(RoleName::Superadmin->value)
                || ($user->organization_id !== null
                    && $user->organization_id === $service->organization_id));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PriceOption $priceOption): bool
    {
        return $this->view($user, $priceOption);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PriceOption $priceOption): bool
    {
        return $this->view($user, $priceOption);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PriceOption $priceOption): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PriceOption $priceOption): bool
    {
        return false;
    }
}
