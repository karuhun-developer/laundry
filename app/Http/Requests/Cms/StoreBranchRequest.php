<?php

namespace App\Http\Requests\Cms;

use App\Enums\RoleName;
use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Branch::class) === true;
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
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'alpha_dash',
                'max:24',
                Rule::unique('branches')->where('organization_id', $organizationId),
            ],
            'phone' => ['nullable', 'string', 'max:24'],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'free_delivery_radius_meters' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'delivery_fee_per_km_rupiah' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function organizationId(): int
    {
        return $this->user()->hasRole(RoleName::Superadmin->value)
            ? $this->integer('organization_id')
            : (int) $this->user()->organization_id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge([
                'code' => Str::upper($this->string('code')->toString()),
            ]);
        }
    }
}
