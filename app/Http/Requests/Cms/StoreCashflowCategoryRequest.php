<?php

namespace App\Http\Requests\Cms;

use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Enums\RoleName;
use App\Models\CashflowCategory;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashflowCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashflowCategory::class) === true;
    }

    /**
     * Prepare inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        /** @var User|null $user */
        $user = $this->user();

        if ($user !== null && ! $user->hasRole(RoleName::Superadmin->value)) {
            $this->merge([
                'organization_id' => $user->organization_id,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->input('organization_id');
        $type = $this->input('type');

        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::notIn(['Pembayaran Pesanan']),
                Rule::unique('cashflow_categories', 'name')
                    ->where('organization_id', $organizationId)
                    ->where('type', $type),
            ],
            'type' => ['required', Rule::enum(CashflowType::class)],
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
}
