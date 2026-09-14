<?php

namespace App\Http\Requests;

use App\Rules\ValidWhatsAppPhone;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CustomerLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'whatsapp_phone' => ['required', 'string', 'max:24', new ValidWhatsAppPhone],
            'pin' => ['required', 'digits:6'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = app(WhatsAppPhoneNormalizer::class)->normalize($this->input('whatsapp_phone'));

        if ($phone !== null) {
            $this->merge(['whatsapp_phone' => $phone]);
        }
    }

    public function throttleKey(): string
    {
        return 'customer-login:'.Str::transliterate(
            Str::lower((string) $this->input('whatsapp_phone')).'|'.$this->ip(),
        );
    }
}
