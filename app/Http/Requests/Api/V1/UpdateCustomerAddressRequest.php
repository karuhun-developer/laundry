<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CustomerAddress;
use App\Rules\ValidWhatsAppPhone;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

#[FailOnUnknownFields]
class UpdateCustomerAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $address = $this->route('address');

        if (! $address instanceof CustomerAddress) {
            return false;
        }

        Gate::authorize('update', $address);

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
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'whatsapp_phone' => ['required', 'string', 'max:30', new ValidWhatsAppPhone],
            'address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
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
