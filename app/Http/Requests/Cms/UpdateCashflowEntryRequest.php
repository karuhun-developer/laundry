<?php

namespace App\Http\Requests\Cms;

use App\Enums\CashflowType;
use App\Enums\RoleName;
use App\Models\CashflowEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashflowEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('cashflowEntry');

        return $entry instanceof CashflowEntry
            && $entry->payment_id === null
            && ($this->user()?->can('update', $entry) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $entry = $this->route('cashflowEntry');

        if ($entry instanceof CashflowEntry && $entry->type === CashflowType::Debit) {
            return [
                'organization_id' => ['prohibited'],
                'branch_id' => ['prohibited'],
                'cashflow_category_id' => ['prohibited'],
                'type' => ['prohibited'],
                'amount_rupiah' => ['required', 'integer', 'min:1'],
                'description' => ['prohibited'],
                'entry_date' => ['required', 'date'],
            ];
        }

        $organizationRules = ['required', 'integer', 'exists:organizations,id'];
        $user = $this->user();

        if ($user !== null && ! $user->hasRole(RoleName::Superadmin->value)) {
            $organizationRules[] = Rule::in([$user->organization_id]);
        }

        return [
            'organization_id' => $organizationRules,
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('organization_id', $this->integer('organization_id')),
                ),
            ],
            'cashflow_category_id' => [
                'required',
                'integer',
                Rule::exists('cashflow_categories', 'id')->where(
                    fn ($query) => $query
                        ->where('organization_id', $this->integer('organization_id'))
                        ->where('type', $this->input('type')),
                ),
            ],
            'type' => ['required', Rule::enum(CashflowType::class)],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
            'entry_date' => ['required', 'date'],
        ];
    }
}
