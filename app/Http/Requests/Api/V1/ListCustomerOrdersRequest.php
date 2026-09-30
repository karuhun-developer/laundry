<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Customer->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'paginated' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'branch_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'queue' => ['sometimes', Rule::in(['all', 'active', 'history'])],
            'unpaid' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => [
                'sometimes',
                'date_format:Y-m-d',
                Rule::when($this->filled('date_from'), ['after_or_equal:date_from']),
            ],
        ];
    }
}
