<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StoreOrderStatusEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order
            && $this->user()?->can('updateStatus', $order) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Status tujuan wajib diisi.',
            'status.enum' => 'Status tujuan tidak dikenali.',
            'note.max' => 'Catatan status maksimal 500 karakter.',
        ];
    }
}
