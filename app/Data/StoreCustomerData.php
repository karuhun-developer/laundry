<?php

namespace App\Data;

use App\Http\Requests\Api\V1\StoreCustomerRequest;

final readonly class StoreCustomerData
{
    public function __construct(
        public string $name,
        public string $whatsappPhone,
    ) {}

    public static function fromRequest(StoreCustomerRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            whatsappPhone: $request->string('whatsapp_phone')->toString(),
        );
    }
}
