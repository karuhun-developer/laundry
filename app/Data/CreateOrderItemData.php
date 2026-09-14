<?php

namespace App\Data;

final readonly class CreateOrderItemData
{
    public function __construct(
        public int $priceOptionId,
        public ?int $weightGrams,
    ) {}
}
