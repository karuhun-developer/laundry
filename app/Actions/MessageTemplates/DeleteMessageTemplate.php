<?php

namespace App\Actions\MessageTemplates;

use App\Models\MessageTemplate;

final class DeleteMessageTemplate
{
    public function handle(MessageTemplate $messageTemplate): void
    {
        $messageTemplate->delete();
    }
}
