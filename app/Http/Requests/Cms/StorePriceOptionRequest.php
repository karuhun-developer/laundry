<?php

namespace App\Http\Requests\Cms;

use App\Enums\PriceType;
use App\Models\PriceOption;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            && $this->user()?->can('create', [PriceOption::class, $service]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $service = $this->route('service');
        $organizationId = $service instanceof Service ? $service->organization_id : 0;
        $serviceId = $service instanceof Service ? $service->id : 0;
        $branchId = $this->filled('branch_id') ? $this->integer('branch_id') : null;
        $isFixedPackage = $this->string('type')->toString() === PriceType::FixedPackage->value;

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organizationId),
            ],
            'type' => ['required', Rule::enum(PriceType::class)],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('price_options', 'name')->where(
                    function (Builder $query) use ($serviceId, $branchId): Builder {
                        $query->where('service_id', $serviceId);

                        return $branchId === null
                            ? $query->whereNull('branch_id')
                            : $query->where('branch_id', $branchId);
                    },
                ),
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
}
