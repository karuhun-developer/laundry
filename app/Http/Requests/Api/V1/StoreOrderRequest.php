<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $branch = $this->route('branch');

        return $organization instanceof Organization
            && $branch instanceof Branch
            && $this->user()?->can('create', [Order::class, $organization, $branch]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isCustomer = $this->user()?->hasRole(RoleName::Customer->value) === true;

        return [
            'customer_id' => [
                Rule::requiredIf(! $isCustomer),
                Rule::prohibitedIf($isCustomer),
                'integer',
                'exists:users,id',
            ],
            'items' => ['required', 'array', 'between:1,20'],
            'items.*.price_option_id' => [
                'required',
                'integer',
                'distinct',
                'exists:price_options,id',
            ],
            'items.*.weight_grams' => ['required', 'integer', 'between:1,1000000'],
            'delivery' => ['sometimes', 'array'],
            'delivery.latitude' => [
                'required_with:delivery.longitude',
                'numeric',
                'between:-90,90',
            ],
            'delivery.longitude' => [
                'required_with:delivery.latitude',
                'numeric',
                'between:-180,180',
            ],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branch = $this->route('branch');

                if ($this->has('delivery')
                    && $branch instanceof Branch
                    && ($branch->latitude === null || $branch->longitude === null)) {
                    $validator->errors()->add(
                        'delivery',
                        'Cabang belum memiliki koordinat untuk menghitung biaya antar jemput.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer wajib dipilih oleh petugas.',
            'customer_id.prohibited' => 'Customer hanya dapat membuat pesanan untuk dirinya sendiri.',
            'items.required' => 'Minimal satu item laundry wajib diisi.',
            'items.between' => 'Jumlah item laundry harus antara 1 sampai 20.',
            'items.*.price_option_id.distinct' => 'Pilihan harga tidak boleh diduplikasi dalam satu pesanan.',
            'items.*.weight_grams.between' => 'Berat harus antara 1 gram sampai 1.000 kilogram.',
            'delivery.latitude.required_with' => 'Latitude dan longitude harus dikirim bersamaan.',
            'delivery.longitude.required_with' => 'Latitude dan longitude harus dikirim bersamaan.',
        ];
    }
}
