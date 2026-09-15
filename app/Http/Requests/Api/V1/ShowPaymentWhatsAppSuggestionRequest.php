<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class ShowPaymentWhatsAppSuggestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');
        $payment = $this->route('payment');
        $user = $this->user();

        return $order instanceof Order
            && $payment instanceof Payment
            && $user instanceof User
            && $user->hasRole(RoleName::Customer->value)
            && $order->customer_id === $user->id
            && $payment->order_id === $order->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
