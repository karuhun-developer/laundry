<?php

namespace App\Http\Requests\Cms;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->branch();

        return $branch !== null && $this->user()?->can('update', $branch) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $branch = $this->branch();

        return [
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'alpha_dash',
                'max:24',
                Rule::unique('branches')
                    ->where('organization_id', $branch?->organization_id)
                    ->ignore($branch),
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
        return (int) $this->branch()?->organization_id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper($this->string('code')->toString())]);
        }
    }

    private function branch(): ?Branch
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch ? $branch : null;
    }
}
