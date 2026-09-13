<?php

namespace App\Http\Requests\Cms;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->user()?->can('delete', $organization) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in([$this->organization()?->name])]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Ketik nama organisasi persis untuk mengonfirmasi penghapusan.'];
    }

    private function organization(): ?Organization
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization : null;
    }
}
