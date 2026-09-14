<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class StoreOrderPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order
            && $this->user()?->can('confirmPricing', $order) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'price_option_id' => ['required', 'integer', 'exists:price_options,id'],
            'weight_grams' => ['required', 'integer', 'between:1,1000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'price_option_id.required' => 'Pilihan layanan wajib tersedia.',
            'price_option_id.exists' => 'Pilihan layanan tidak ditemukan.',
            'weight_grams.required' => 'Berat aktual laundry wajib diisi.',
            'weight_grams.between' => 'Berat harus antara 1 gram sampai 1.000 kilogram.',
        ];
    }
}
