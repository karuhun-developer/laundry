<?php

namespace App\Http\Requests\Cms;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->service();

        return $service !== null && $this->user()?->can('delete', $service) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in([$this->service()?->name])]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Ketik nama layanan persis untuk mengonfirmasi penghapusan.'];
    }

    private function service(): ?Service
    {
        $service = $this->route('service');

        return $service instanceof Service ? $service : null;
    }
}
