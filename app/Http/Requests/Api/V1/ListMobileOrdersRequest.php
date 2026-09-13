<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RoleName;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListMobileOrdersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $user = $this->user();

        if (! $organization instanceof Organization || $user === null) {
            return false;
        }

        if ($user->hasRole(RoleName::Customer->value)) {
            return $user->organization_id === null
                || $user->organization_id === $organization->id;
        }

        return $user->hasRole(RoleName::BranchAdmin->value)
            && $user->organization_id === $organization->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
