<?php

namespace App\Http\Requests\Cms;

use App\Enums\CashflowGroup;
use App\Models\CashflowCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashflowCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->category();

        if ($category === null || $category->name === 'Pembayaran Pesanan') {
            return false;
        }

        return $this->user()?->can('update', $category) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->category();
        $organizationId = $category?->organization_id;
        $type = $category?->type?->value;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::notIn(['Pembayaran Pesanan']),
                Rule::unique('cashflow_categories', 'name')
                    ->ignore($category?->id)
                    ->where('organization_id', $organizationId)
                    ->where('type', $type),
            ],
            'group' => ['required', Rule::enum(CashflowGroup::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.not_in' => 'Nama "Pembayaran Pesanan" dilindungi oleh sistem.',
            'name.unique' => 'Kategori dengan nama dan tipe ini sudah ada.',
        ];
    }

    public function category(): ?CashflowCategory
    {
        $category = $this->route('cashflowCategory');

        return $category instanceof CashflowCategory ? $category : null;
    }
}
