<?php

namespace App\Http\Requests\Cms;

use App\Models\User;
use App\Services\WhatsAppPhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        $user = $this->managedUser();
        $allowed = array_values(array_filter([
            $user?->email,
            $user?->whatsapp_phone,
            $user?->name,
        ]));

        return [
            'confirmation' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($user, $allowed): void {
                    if (! is_string($value)) {
                        $fail('Ketik email, nomor WhatsApp, atau nama pengguna persis untuk mengonfirmasi penghapusan.');

                        return;
                    }

                    $trimmed = trim($value);
                    if (in_array($trimmed, $allowed, true)) {
                        return;
                    }

                    if ($user?->whatsapp_phone !== null) {
                        $normalizedInput = app(WhatsAppPhoneNormalizer::class)->normalize($trimmed);
                        if ($normalizedInput !== null && $normalizedInput === $user->whatsapp_phone) {
                            return;
                        }
                    }

                    $fail('Ketik email, WhatsApp, atau nama pengguna persis untuk mengonfirmasi penghapusan.');
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.required' => 'Ketik email, WhatsApp, atau nama pengguna persis untuk mengonfirmasi penghapusan.',
        ];
    }

    private function managedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
