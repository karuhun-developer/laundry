<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole(RoleName::Customer->value)) {
            return $order->customer_id === $user->id;
        }

        return $this->staffCanAccessOrder($user, $order, PermissionName::ManageOrders);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organization $organization, Branch $branch): bool
    {
        if ($branch->organization_id !== $organization->id) {
            return false;
        }

        if ($user->hasRole(RoleName::Customer->value)) {
            return $user->organization_id === null
                || $user->organization_id === $organization->id;
        }

        if (! $user->can(PermissionName::ManageOrders->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Superadmin->value)) {
            return true;
        }

        if ($user->organization_id === null || $user->organization_id !== $organization->id) {
            return false;
        }

        if ($user->hasRole(RoleName::BranchAdmin->value)) {
            return $user->branches()->whereKey($branch->id)->exists();
        }

        return $user->hasRole(RoleName::Owner->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return $this->updateStatus($user, $order);
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->staffCanAccessOrder($user, $order, PermissionName::UpdateOrderStatus);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }

    private function staffCanAccessOrder(
        User $user,
        Order $order,
        PermissionName $permission,
    ): bool {
        if (! $user->can($permission->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Superadmin->value)) {
            return true;
        }

        if ($user->organization_id !== $order->organization_id) {
            return false;
        }

        if ($user->hasRole(RoleName::BranchAdmin->value)) {
            return $user->branches()->whereKey($order->branch_id)->exists();
        }

        return $user->hasRole(RoleName::Owner->value);
    }
}
