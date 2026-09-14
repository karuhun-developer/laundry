<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Rules\ValidWhatsAppPhone;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

#[FailOnUnknownFields]
class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('createCustomer', User::class);

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
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_phone' => ['required', 'string', 'max:30', new ValidWhatsAppPhone],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('whatsapp_phone');

        if (! is_string($phone)) {
            return;
        }

        $normalizedPhone = app(WhatsAppPhoneNormalizer::class)->normalize($phone);

        if ($normalizedPhone !== null) {
            $this->merge(['whatsapp_phone' => $normalizedPhone]);
        }
    }
}
