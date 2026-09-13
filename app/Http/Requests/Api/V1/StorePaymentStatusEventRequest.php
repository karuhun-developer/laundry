<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StorePaymentStatusEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');
        $payment = $this->route('payment');

        return $order instanceof Order
            && $payment instanceof Payment
            && $payment->order_id === $order->id
            && $this->user()?->can('updateStatus', $order) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PaymentStatus::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Status pembayaran wajib dipilih.',
            'status.enum' => 'Status pembayaran tidak dikenali.',
            'note.max' => 'Catatan pembayaran maksimal 500 karakter.',
        ];
    }
}
