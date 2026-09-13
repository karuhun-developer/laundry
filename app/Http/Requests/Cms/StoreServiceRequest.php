<?php

namespace App\Http\Requests\Cms;

use App\Enums\RoleName;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->string('code')->toString())]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Service::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->organizationId();

        return [
            'organization_id' => [
                Rule::requiredIf($this->user()?->hasRole(RoleName::Superadmin->value) === true),
                'nullable',
                'integer',
                'exists:organizations,id',
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'alpha_dash',
                'max:32',
                Rule::unique('services', 'code')->where('organization_id', $organizationId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function organizationId(): int
    {
        return $this->user()->hasRole(RoleName::Superadmin->value)
            ? $this->integer('organization_id')
            : (int) $this->user()->organization_id;
    }
}
