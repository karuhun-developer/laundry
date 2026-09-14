<?php

namespace App\Data;

use App\Http\Requests\Api\V1\UpdateCustomerRequest;

final readonly class UpdateCustomerData
{
    public function __construct(
        public ?string $name,
        public ?string $whatsappPhone,
        public bool $updatesName,
        public bool $updatesWhatsappPhone,
    ) {}

    public static function fromRequest(UpdateCustomerRequest $request): self
    {
        return new self(
            name: $request->has('name') ? $request->string('name')->toString() : null,
            whatsappPhone: $request->has('whatsapp_phone')
                ? $request->string('whatsapp_phone')->toString()
                : null,
            updatesName: $request->has('name'),
            updatesWhatsappPhone: $request->has('whatsapp_phone'),
        );
    }

    /** @return array{name?: string, whatsapp_phone?: string} */
    public function attributes(): array
    {
        $attributes = [];

        if ($this->updatesName && $this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->updatesWhatsappPhone && $this->whatsappPhone !== null) {
            $attributes['whatsapp_phone'] = $this->whatsappPhone;
        }

        return $attributes;
    }
}
