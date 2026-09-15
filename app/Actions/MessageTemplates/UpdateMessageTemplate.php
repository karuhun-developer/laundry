<?php

namespace App\Actions\MessageTemplates;

use App\Data\MessageTemplateData;
use App\Enums\RoleName;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class UpdateMessageTemplate
{
    public function handle(
        User $actor,
        MessageTemplate $messageTemplate,
        MessageTemplateData $data,
    ): MessageTemplate {
        if (! $actor->hasRole(RoleName::Superadmin->value)) {
            if ($data->branchId === null) {
                throw new AuthorizationException('Owner hanya dapat mengubah template khusus cabang.');
            }

            return MessageTemplate::query()->updateOrCreate(
                [
                    'organization_id' => $data->organizationId,
                    'scope_key' => $data->scopeKey,
                    'order_status' => $data->orderStatus,
                ],
                [
                    'branch_id' => $data->branchId,
                    'body' => $data->body,
                    'is_active' => $data->isActive,
                ],
            );
        }

        $messageTemplate->update([
            'branch_id' => $data->branchId,
            'scope_key' => $data->scopeKey,
            'order_status' => $data->orderStatus,
            'body' => $data->body,
            'is_active' => $data->isActive,
        ]);

        return $messageTemplate->refresh();
    }
}
