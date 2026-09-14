<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function createCustomer(User $user): Response
    {
        return $this->isBranchAdminWithOrganization($user)
            ? Response::allow()
            : Response::deny();
    }

    public function updateCustomer(User $user, User $customer): Response
    {
        return $this->globalCustomerManagementResponse($user, $customer);
    }

    public function resetCustomerPin(User $user, User $customer): Response
    {
        return $this->globalCustomerManagementResponse($user, $customer);
    }

    public function detachCustomer(User $user, User $customer): Response
    {
        return $this->customerManagementResponse($user, $customer);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageUsers->value)
            && ($user->hasRole(RoleName::Superadmin->value) || $user->organization_id !== null);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if (! $user->can(PermissionName::ManageUsers->value)
            || $model->hasRole(RoleName::Superadmin->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Superadmin->value)) {
            return true;
        }

        return ! $model->hasRole(RoleName::Owner->value)
            && $user->organization_id !== null
            && $user->organization_id === $model->organization_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $this->view($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $this->view($user, $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function customerManagementResponse(User $user, User $customer): Response
    {
        if (! $this->isBranchAdminWithOrganization($user)
            || ! $customer->hasRole(RoleName::Customer->value)
            || $customer->roles()->where('name', '!=', RoleName::Customer->value)->exists()
            || ! $customer->customerOrganizations()->whereKey($user->organization_id)->exists()) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    private function globalCustomerManagementResponse(User $user, User $customer): Response
    {
        $managementResponse = $this->customerManagementResponse($user, $customer);

        if ($managementResponse->denied()
            || $customer->customerOrganizations()->count() !== 1) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    private function isBranchAdminWithOrganization(User $user): bool
    {
        return $user->hasRole(RoleName::BranchAdmin->value)
            && $user->organization_id !== null;
    }
}
