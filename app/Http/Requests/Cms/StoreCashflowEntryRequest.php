<?php

namespace App\Http\Requests\Cms;

use App\Enums\CashflowType;
use App\Models\CashflowEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashflowEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashflowEntry::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'cashflow_category_id' => ['required', 'integer', 'exists:cashflow_categories,id'],
            'type' => ['required', Rule::enum(CashflowType::class)],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
            'entry_date' => ['required', 'date'],
        ];
    }
}
