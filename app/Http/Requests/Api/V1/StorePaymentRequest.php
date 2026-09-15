<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order
            && $this->user()?->can('createPayment', [
                $order,
                (string) $this->input('method'),
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
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_rupiah' => ['required', 'integer', 'between:1,9999999999999'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gateway_driver' => [
                Rule::requiredIf($this->input('method') === PaymentMethod::Gateway->value),
                Rule::prohibitedIf($this->input('method') !== PaymentMethod::Gateway->value),
                Rule::enum(PaymentGatewayDriver::class),
            ],
            'gateway_method' => [
                Rule::requiredIf($this->input('method') === PaymentMethod::Gateway->value),
                Rule::prohibitedIf($this->input('method') !== PaymentMethod::Gateway->value),
                'string',
                'max:40',
                Rule::notIn(['VA', 'ALL']),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.enum' => 'Metode pembayaran tidak dikenali.',
            'amount_rupiah.between' => 'Nominal pembayaran harus lebih dari nol.',
            'note.max' => 'Catatan pembayaran maksimal 500 karakter.',
            'gateway_driver.required' => 'Penyedia payment gateway wajib dipilih.',
            'gateway_method.required' => 'Metode payment gateway wajib dipilih.',
            'gateway_method.not_in' => 'Pilih QRIS atau virtual account bank secara langsung.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $gatewayMethod = $this->input('gateway_method');

        if (is_string($gatewayMethod)) {
            $this->merge(['gateway_method' => strtoupper(trim($gatewayMethod))]);
        }
    }
}
