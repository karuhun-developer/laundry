<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use App\Models\PriceOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
            'custom_price_per_kilogram_rupiah' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('custom_price_per_kilogram_rupiah') && $this->filled('price_option_id')) {
                    $priceOption = PriceOption::query()->find($this->integer('price_option_id'));
                    if ($priceOption) {
                        $floor = $priceOption->effectiveMinPricePerKilogram();
                        if ($floor !== null && $this->integer('custom_price_per_kilogram_rupiah') < $floor) {
                            $validator->errors()->add(
                                'custom_price_per_kilogram_rupiah',
                                'Harga per kilogram tidak boleh lebih rendah dari harga bawah (Rp '.number_format($floor, 0, ',', '.').').',
                            );
                        }
                    }
                }
            },
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
