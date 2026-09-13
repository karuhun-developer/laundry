<?php

namespace App\Http\Requests\Cms;

use App\Enums\OrderStatus;
use App\Models\MessageTemplate;
use App\Rules\SupportedMessageTemplateVariables;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $messageTemplate = $this->messageTemplate();

        return $messageTemplate !== null
            && $this->user()?->can('update', $messageTemplate) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $messageTemplate = $this->messageTemplate();

        return [
            'organization_id' => ['prohibited'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $messageTemplate?->organization_id),
            ],
            'order_status' => [
                'required',
                Rule::enum(OrderStatus::class),
                Rule::unique('message_templates', 'order_status')
                    ->where('organization_id', $messageTemplate?->organization_id)
                    ->where('scope_key', $this->scopeKey())
                    ->ignore($messageTemplate),
            ],
            'body' => ['required', 'string', 'max:4000', new SupportedMessageTemplateVariables],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function organizationId(): int
    {
        return (int) $this->messageTemplate()?->organization_id;
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
            'branch_id.exists' => 'Cabang harus berasal dari organisasi template.',
            'order_status.unique' => 'Template untuk cakupan dan status ini sudah tersedia.',
        ];
    }

    private function messageTemplate(): ?MessageTemplate
    {
        $messageTemplate = $this->route('messageTemplate');

        return $messageTemplate instanceof MessageTemplate ? $messageTemplate : null;
    }
}
