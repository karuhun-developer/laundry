<?php

namespace App\Http\Requests\Cms;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->branch();

        return $branch !== null && $this->user()?->can('delete', $branch) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in([$this->branch()?->name])]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Ketik nama cabang persis untuk mengonfirmasi penghapusan.'];
    }

    private function branch(): ?Branch
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch ? $branch : null;
    }
}
