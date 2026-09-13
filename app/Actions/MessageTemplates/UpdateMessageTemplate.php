<?php

namespace App\Actions\MessageTemplates;

use App\Data\MessageTemplateData;
use App\Models\MessageTemplate;

final class UpdateMessageTemplate
{
    public function handle(MessageTemplate $messageTemplate, MessageTemplateData $data): MessageTemplate
    {
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
