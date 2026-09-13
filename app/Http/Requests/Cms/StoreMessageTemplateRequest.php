<?php

namespace App\Http\Requests\Cms;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\MessageTemplate;
use App\Rules\SupportedMessageTemplateVariables;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', MessageTemplate::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->organizationId();

        return [
            'organization_id' => [
                Rule::requiredIf($this->user()?->hasRole(RoleName::Superadmin->value) === true),
                'nullable',
                'integer',
                'exists:organizations,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organizationId),
            ],
            'order_status' => [
                'required',
                Rule::enum(OrderStatus::class),
                Rule::unique('message_templates', 'order_status')
                    ->where('organization_id', $organizationId)
                    ->where('scope_key', $this->scopeKey()),
            ],
            'body' => ['required', 'string', 'max:4000', new SupportedMessageTemplateVariables],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function organizationId(): int
    {
        return $this->user()->hasRole(RoleName::Superadmin->value)
            ? $this->integer('organization_id')
            : (int) $this->user()->organization_id;
    }

    public function branchId(): ?int
    {
        return $this->filled('branch_id') ? $this->integer('branch_id') : null;
    }

    public function scopeKey(): string
    {
        $branchId = $this->branchId();

        return $branchId === null ? 'organization' : 'branch:'.$branchId;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'branch_id.exists' => 'Cabang harus berasal dari organisasi yang dipilih.',
            'order_status.unique' => 'Template untuk cakupan dan status ini sudah tersedia.',
        ];
    }
}
