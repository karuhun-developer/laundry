<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class MobileContextRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            RoleName::Customer->value,
            RoleName::BranchAdmin->value,
        ]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization' => ['sometimes', 'string', 'exists:organizations,slug'],
        ];
    }
}
