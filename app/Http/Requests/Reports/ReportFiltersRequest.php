<?php

namespace App\Http\Requests\Reports;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFiltersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isSuperadmin = $user?->hasRole(RoleName::Superadmin->value) === true;
        $organizationId = $isSuperadmin
            ? ($this->filled('organization_id') ? $this->integer('organization_id') : null)
            : $user?->organization_id;
        $cityId = $this->filled('city_id') ? $this->integer('city_id') : null;

        return [
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'organization_id' => [
                Rule::prohibitedIf(! $isSuperadmin),
                'nullable',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($organizationId, $cityId): void {
                    $query
                        ->when($organizationId !== null, fn ($builder) => $builder->where('organization_id', $organizationId))
                        ->when($cityId !== null, fn ($builder) => $builder->where('city_id', $cityId));
                }),
            ],
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(function ($query) use ($organizationId): void {
                    $query->when(
                        $organizationId !== null,
                        fn ($builder) => $builder->where('organization_id', $organizationId),
                    );
                }),
            ],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('date_from')) {
            $this->merge(['date_from' => now()->subDays(29)->toDateString()]);
        }

        if (! $this->has('date_to')) {
            $this->merge(['date_to' => now()->toDateString()]);
        }
    }
}
