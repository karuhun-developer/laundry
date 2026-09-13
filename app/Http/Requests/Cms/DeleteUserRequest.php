<?php

namespace App\Http\Requests\Cms;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->managedUser();

        return $user !== null && $this->user()?->can('delete', $user) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in([$this->managedUser()?->email])]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Ketik email pengguna persis untuk mengonfirmasi penghapusan.'];
    }

    private function managedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
