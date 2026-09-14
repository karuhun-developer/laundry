<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Rules\ValidWhatsAppPhone;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        if (! $customer instanceof User) {
            return false;
        }

        Gate::authorize('updateCustomer', $customer);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = $customer instanceof User ? $customer->id : null;

        return [
            'name' => ['required_without:whatsapp_phone', 'string', 'max:255'],
            'whatsapp_phone' => [
                'required_without:name',
                'string',
                'max:30',
                new ValidWhatsAppPhone,
                Rule::unique('users', 'whatsapp_phone')->ignore($customerId),
            ],
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
