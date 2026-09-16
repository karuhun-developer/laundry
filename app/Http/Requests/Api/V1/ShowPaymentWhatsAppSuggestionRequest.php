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

        if (! ($order instanceof Order && $payment instanceof Payment && $user instanceof User)) {
            return false;
        }

        if ($payment->order_id !== $order->id) {
            return false;
        }

        if ($user->hasRole(RoleName::Customer->value)) {
            return $order->customer_id === $user->id;
        }

        return $user->can('updateStatus', $order) || $user->can('view', $order);
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
