<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\CashflowCategory;
use App\Models\User;

class CashflowCategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageCashflow->value)
            && ($user->hasRole(RoleName::Superadmin->value) || $user->organization_id !== null);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageCashflow->value)
            && ($user->hasRole(RoleName::Superadmin->value) || $user->organization_id !== null);
    }

    /**
     * Determine whether the user can update the model.
     * Category 'Pembayaran Pesanan' can never be updated.
     */
    public function update(User $user, CashflowCategory $category): bool
    {
        if ($category->name === 'Pembayaran Pesanan') {
            return false;
        }

        if (! $user->can(PermissionName::ManageCashflow->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Superadmin->value)) {
            return true;
        }

        return $user->organization_id === $category->organization_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Category 'Pembayaran Pesanan' or categories with existing entries can never be deleted.
     */
    public function delete(User $user, CashflowCategory $category): bool
    {
        if ($category->name === 'Pembayaran Pesanan' || $category->is_system) {
            return false;
        }

        if ($category->entries()->exists()) {
            return false;
        }

        if (! $user->can(PermissionName::ManageCashflow->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Superadmin->value)) {
            return true;
        }

        return $user->organization_id === $category->organization_id;
    }
}
