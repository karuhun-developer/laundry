<?php

namespace App\Http\Requests\Cms;

use App\Enums\PriceType;
use App\Models\PriceOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priceOption = $this->priceOption();

        return $priceOption !== null && $this->user()?->can('update', $priceOption) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $priceOption = $this->priceOption();
        $service = $priceOption?->service;
        $branchId = $this->integer('branch_id');
        $isFixedPackage = $this->string('type')->toString() === PriceType::FixedPackage->value;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $service->organization_id ?? 0),
            ],
            'type' => ['required', Rule::enum(PriceType::class)],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('price_options', 'name')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where('service_id', $service->id ?? 0)
                            ->where('branch_id', $branchId),
                    )
                    ->ignore($priceOption),
            ],
            'package_weight_grams' => [
                Rule::requiredIf($isFixedPackage),
                Rule::prohibitedIf(! $isFixedPackage),
                'nullable',
                'integer',
                'min:1',
                'max:1000000',
            ],
            'base_price_rupiah' => [
                Rule::requiredIf($isFixedPackage),
                Rule::prohibitedIf(! $isFixedPackage),
                'nullable',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'price_per_kilogram_rupiah' => [
                Rule::requiredIf(! $isFixedPackage),
                'nullable',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'minimum_chargeable_grams' => [
                Rule::prohibitedIf($isFixedPackage),
                'nullable',
                'integer',
                'min:1',
                'max:1000000',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function priceOption(): ?PriceOption
    {
        $priceOption = $this->route('priceOption');

        return $priceOption instanceof PriceOption ? $priceOption : null;
    }
}
