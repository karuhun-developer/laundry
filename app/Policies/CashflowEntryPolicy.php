<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\CashflowEntry;
use App\Models\User;

class CashflowEntryPolicy
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
     * Only manual entries (no linked payment) can be updated.
     */
    public function update(User $user, CashflowEntry $entry): bool
    {
        return $this->canManageManualEntry($user, $entry);
    }

    /**
     * Determine whether the user can delete the model.
     * Only manual entries (no linked payment) can be deleted.
     */
    public function delete(User $user, CashflowEntry $entry): bool
    {
        return $this->canManageManualEntry($user, $entry);
    }

    private function canManageManualEntry(User $user, CashflowEntry $entry): bool
    {
        if ($entry->payment_id !== null) {
            return false;
        }

        if (! $user->can(PermissionName::ManageCashflow->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Superadmin->value)) {
            return true;
        }

        return $user->organization_id === $entry->organization_id;
    }
}
