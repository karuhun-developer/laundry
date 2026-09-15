<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerAddressPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        return $this->isCustomer($user)
            ? Response::allow()
            : Response::deny();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerAddress $customerAddress): Response
    {
        return $this->ownsAddress($user, $customerAddress)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerAddress $customerAddress): Response
    {
        return $this->view($user, $customerAddress);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerAddress $customerAddress): Response
    {
        return $this->view($user, $customerAddress);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerAddress $customerAddress): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerAddress $customerAddress): bool
    {
        return false;
    }

    private function ownsAddress(User $user, CustomerAddress $customerAddress): bool
    {
        return $this->isCustomer($user) && $customerAddress->customer_id === $user->id;
    }

    private function isCustomer(User $user): bool
    {
        return $user->hasRole(RoleName::Customer->value)
            && ! $user->roles()->where('name', '!=', RoleName::Customer->value)->exists();
    }
}
