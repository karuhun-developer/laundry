<?php

declare(strict_types=1);

namespace App\DTOs\Qris;

use Carbon\CarbonImmutable;

final readonly class QrisCheckResponse
{
    public function __construct(
        public bool $paid,
        public string $status,
        public ?CarbonImmutable $settledAt = null,
        public array $raw = [],
    ) {}
}
