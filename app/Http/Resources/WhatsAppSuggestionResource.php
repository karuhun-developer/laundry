<?php

namespace App\Http\Resources;

use App\Data\WhatsAppSuggestionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppSuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WhatsAppSuggestionData $suggestion */
        $suggestion = $this->resource;

        return [
            'available' => $suggestion->available,
            'phone' => $suggestion->phone,
            'message' => $suggestion->message,
            'url' => $suggestion->url,
            'template_id' => $suggestion->templateId,
            'unavailable_reason' => $suggestion->unavailableReason,
        ];
    }
}
