<?php

namespace App\Data;

use App\Models\User;
use Carbon\CarbonInterface;

final readonly class CustomerCredentialData
{
    public function __construct(
        public User $customer,
        public string $temporaryPin,
        public CarbonInterface $expiresAt,
    ) {}
}
