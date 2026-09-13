<?php

namespace App\Data;

final readonly class WhatsAppSuggestionData
{
    public function __construct(
        public bool $available,
        public ?string $phone,
        public ?string $message,
        public ?string $url,
        public ?int $templateId,
        public ?string $unavailableReason,
    ) {}

    public static function unavailable(string $reason): self
    {
        return new self(false, null, null, null, null, $reason);
    }
}
