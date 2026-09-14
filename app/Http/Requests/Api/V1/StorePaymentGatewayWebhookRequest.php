<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentGatewayWebhookRequest extends FormRequest
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
            '_signature' => ['required', 'string', 'max:255'],
            '_header_event' => [
                'required',
                'string',
                Rule::in([
                    'transaction.settlement',
                    'transaction.paid',
                    'transaction.failed',
                    'transaction.cancelled',
                ]),
            ],
            '_delivery_id' => ['required', 'string', 'max:120'],
            'event' => [
                'required',
                'string',
                Rule::in([
                    'transaction.settlement',
                    'transaction.paid',
                    'transaction.failed',
                    'transaction.cancelled',
                ]),
            ],
            'data' => ['required', 'array'],
            'data.id' => ['required', 'string', 'max:100'],
            'data.orderId' => ['required', 'string', 'max:100'],
            'data.amount' => ['required', 'integer', 'min:1'],
            'data.paymentMethod' => ['sometimes', 'nullable', 'string', 'max:40'],
            'data.status' => [
                'required',
                'string',
                Rule::in(['settlement', 'success', 'failed', 'cancelled']),
            ],
            'data.paidAt' => ['sometimes', 'nullable', 'date'],
            'timestamp' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            '_signature' => $this->header('X-Paywuz-Signature'),
            '_header_event' => $this->header('X-Paywuz-Event'),
            '_delivery_id' => $this->header('X-Paywuz-Delivery'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($validator->errors()->hasAny(['_header_event', 'event', 'data.status'])) {
            throw PaymentGatewayException::webhookDataMismatch(
                'Event atau status pembayaran dari webhook tidak dikenali.',
            );
        }

        parent::failedValidation($validator);
    }
}
