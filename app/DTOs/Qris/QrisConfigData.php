<?php

declare(strict_types=1);

namespace App\DTOs\Qris;

final readonly class QrisConfigData
{
    public function __construct(
        public string $apiKey,
        public string $baseUrl = 'https://qris.karuhundeveloper.com',
    ) {}
}
