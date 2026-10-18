<?php

namespace App\Http\Requests\Cms;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->user()?->can('update', $organization) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'qris_enabled' => ['sometimes', 'boolean'],
            'qris_api_key' => ['required', 'string', 'max:255'],
            'qris_base_url' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }

    private function organization(): ?Organization
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization : null;
    }
}
