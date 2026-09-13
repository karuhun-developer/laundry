<?php

namespace App\Data;

use App\Models\Branch;

final readonly class NearestBranchData
{
    public function __construct(
        public Branch $branch,
        public DeliveryQuoteData $deliveryQuote,
    ) {}
}
