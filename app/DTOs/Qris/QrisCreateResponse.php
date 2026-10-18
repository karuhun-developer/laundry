<?php

declare(strict_types=1);

namespace App\DTOs\Qris;

use Carbon\CarbonImmutable;

final readonly class QrisCreateResponse
{
    public function __construct(
        public string $token,
        public int $amount,
        public ?int $uniqueCode,
        public string $qrisString,
        public CarbonImmutable $expiresAt,
        public ?string $qrisUrl = null,
    ) {}
}
