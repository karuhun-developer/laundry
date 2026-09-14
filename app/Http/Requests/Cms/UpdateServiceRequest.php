<?php

namespace App\Http\Requests\Cms;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->service();

        return $service !== null && $this->user()?->can('update', $service) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $service = $this->service();

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'alpha_dash',
                'max:32',
                Rule::unique('services', 'code')
                    ->where('organization_id', $service?->organization_id)
                    ->ignore($service),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function organizationId(): int
    {
        return (int) $this->service()?->organization_id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper($this->string('code')->toString())]);
        }
    }

    private function service(): ?Service
    {
        $service = $this->route('service');

        return $service instanceof Service ? $service : null;
    }
}
