<?php

namespace App\Http\Requests\Cms;

use App\Models\CashflowCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCashflowCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->category();

        if ($category === null) {
            return false;
        }

        if ($category->name === 'Pembayaran Pesanan' || $category->is_system) {
            return false;
        }

        if ($category->entries()->exists()) {
            return false;
        }

        return $this->user()?->can('delete', $category) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function category(): ?CashflowCategory
    {
        $category = $this->route('cashflowCategory');

        return $category instanceof CashflowCategory ? $category : null;
    }
}
