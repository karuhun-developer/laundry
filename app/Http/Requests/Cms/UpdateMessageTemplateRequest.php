<?php

namespace App\Http\Requests\Cms;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
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
        $isSuperadmin = $this->user()?->hasRole(RoleName::Superadmin->value) === true;
        $uniqueStatus = Rule::unique('message_templates', 'order_status')
            ->where('organization_id', $messageTemplate?->organization_id)
            ->where('scope_key', $this->scopeKey());
        $targetTemplateId = $this->targetTemplateId();

        if ($targetTemplateId !== null) {
            $uniqueStatus->ignore($targetTemplateId);
        }

        $branchRules = [
            Rule::requiredIf(! $isSuperadmin),
            'nullable',
            'integer',
            Rule::exists('branches', 'id')->where('organization_id', $messageTemplate?->organization_id),
        ];
        $statusRules = [
            'required',
            Rule::enum(OrderStatus::class),
            $uniqueStatus,
        ];

        if (! $isSuperadmin && $messageTemplate !== null) {
            $statusRules[] = Rule::in([$messageTemplate->order_status->value]);

            if ($messageTemplate->branch_id !== null) {
                $branchRules[] = Rule::in([$messageTemplate->branch_id]);
            }
        }

        return [
            'organization_id' => ['prohibited'],
            'branch_id' => $branchRules,
            'order_status' => $statusRules,
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
            'branch_id.required' => 'Owner wajib memilih cabang.',
            'branch_id.exists' => 'Cabang harus berasal dari organisasi template.',
            'branch_id.in' => 'Cakupan cabang tidak dapat dipindahkan saat mengedit template.',
            'order_status.in' => 'Status pesanan tidak dapat diubah saat mengedit template cabang.',
            'order_status.unique' => 'Template untuk cakupan dan status ini sudah tersedia.',
        ];
    }

    private function targetTemplateId(): ?int
    {
        $messageTemplate = $this->messageTemplate();

        if ($messageTemplate === null) {
            return null;
        }

        if ($this->user()?->hasRole(RoleName::Superadmin->value) === true) {
            return $messageTemplate->id;
        }

        $branchId = $this->branchId();
        $status = OrderStatus::tryFrom($this->string('order_status')->toString());

        if ($branchId === null || $status === null) {
            return null;
        }

        return MessageTemplate::query()
            ->where('organization_id', $messageTemplate->organization_id)
            ->where('scope_key', 'branch:'.$branchId)
            ->where('order_status', $status)
            ->value('id');
    }

    private function messageTemplate(): ?MessageTemplate
    {
        $messageTemplate = $this->route('messageTemplate');

        return $messageTemplate instanceof MessageTemplate ? $messageTemplate : null;
    }
}
