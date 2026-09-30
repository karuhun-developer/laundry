<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
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
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'paginated' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'branch_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'queue' => ['sometimes', Rule::in(['all', 'active', 'history', 'unweighed', 'overdue', 'ready'])],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'unpaid' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
        ];
    }
}
