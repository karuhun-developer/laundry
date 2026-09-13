<?php

namespace App\Http\Requests\Cms;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->managedUser();

        return $user !== null && $this->user()?->can('update', $user) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $managedUser = $this->managedUser();
        $roles = $this->user()?->hasRole(RoleName::Superadmin->value) === true
            ? [RoleName::Owner->value, RoleName::BranchAdmin->value, RoleName::Customer->value]
            : [RoleName::BranchAdmin->value, RoleName::Customer->value];

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($roles)],
            'branch_ids' => [
                Rule::requiredIf($this->string('role')->toString() === RoleName::BranchAdmin->value),
                Rule::prohibitedIf($this->string('role')->toString() !== RoleName::BranchAdmin->value),
                'array',
                'min:1',
            ],
            'branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $managedUser?->organization_id),
            ],
        ];
    }

    private function managedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
