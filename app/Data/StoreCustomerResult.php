<?php

namespace App\Data;

use App\Models\User;

final readonly class StoreCustomerResult
{
    public function __construct(
        public User $customer,
        public ?CustomerCredentialData $credential,
    ) {}
}
