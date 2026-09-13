<?php

namespace App\Http\Requests\Cms;

use App\Enums\RoleName;
use App\Models\City;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', City::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => [
                Rule::requiredIf($this->user()?->hasRole(RoleName::Superadmin->value) === true),
                'nullable',
                'integer',
                'exists:organizations,id',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cities')->where('organization_id', $this->organizationId())
                    ->where('province', $this->string('province')->toString()),
            ],
            'province' => ['required', 'string', 'max:100'],
        ];
    }

    public function organizationId(): int
    {
        return $this->user()->hasRole(RoleName::Superadmin->value)
            ? $this->integer('organization_id')
            : (int) $this->user()->organization_id;
    }
}
