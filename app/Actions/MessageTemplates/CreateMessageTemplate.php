<?php

namespace App\Actions\MessageTemplates;

use App\Data\MessageTemplateData;
use App\Models\MessageTemplate;

final class CreateMessageTemplate
{
    public function handle(MessageTemplateData $data): MessageTemplate
    {
        return MessageTemplate::query()->create([
            'organization_id' => $data->organizationId,
            'branch_id' => $data->branchId,
            'scope_key' => $data->scopeKey,
            'order_status' => $data->orderStatus,
            'body' => $data->body,
            'is_active' => $data->isActive,
        ]);
    }
}
