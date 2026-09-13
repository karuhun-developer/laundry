<?php

namespace App\Http\Requests\Cms;

use App\Enums\RoleName;
use App\Models\User;
use App\Rules\ValidWhatsAppPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->organizationId();
        $roles = $this->user()?->hasRole(RoleName::Superadmin->value) === true
            ? [RoleName::Owner->value, RoleName::BranchAdmin->value, RoleName::Customer->value]
            : [RoleName::BranchAdmin->value, RoleName::Customer->value];

        return [
            'organization_id' => [
                Rule::requiredIf($this->user()?->hasRole(RoleName::Superadmin->value) === true),
                'nullable',
                'integer',
                'exists:organizations,id',
            ],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_phone' => ['nullable', 'string', 'max:24', new ValidWhatsAppPhone],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($roles)],
            'branch_ids' => [
                Rule::requiredIf($this->string('role')->toString() === RoleName::BranchAdmin->value),
                Rule::prohibitedIf($this->string('role')->toString() !== RoleName::BranchAdmin->value),
                'array',
                'min:1',
            ],
            'branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }

    public function organizationId(): int
    {
        return $this->user()->hasRole(RoleName::Superadmin->value)
            ? $this->integer('organization_id')
            : (int) $this->user()->organization_id;
    }
}
