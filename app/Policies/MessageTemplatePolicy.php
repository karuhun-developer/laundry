<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\MessageTemplate;
use App\Models\User;

class MessageTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageMessageTemplates->value)
            && ($user->hasRole(RoleName::Superadmin->value) || $user->organization_id !== null);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->can(PermissionName::ManageMessageTemplates->value)
            && ($user->hasRole(RoleName::Superadmin->value)
                || ($user->organization_id !== null
                    && $user->organization_id === $messageTemplate->organization_id));
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
    public function update(User $user, MessageTemplate $messageTemplate): bool
    {
        return $this->view($user, $messageTemplate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MessageTemplate $messageTemplate): bool
    {
        return $this->view($user, $messageTemplate);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MessageTemplate $messageTemplate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MessageTemplate $messageTemplate): bool
    {
        return false;
    }
}
