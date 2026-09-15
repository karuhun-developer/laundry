<?php

namespace App\Data;

use App\Http\Requests\Api\V1\StoreCustomerAddressRequest;
use App\Http\Requests\Api\V1\UpdateCustomerAddressRequest;

final readonly class CustomerAddressData
{
    public function __construct(
        public string $label,
        public string $recipientName,
        public string $whatsappPhone,
        public string $address,
        public ?string $notes,
        public float $latitude,
        public float $longitude,
        public bool $isDefault,
    ) {}

    public static function fromRequest(StoreCustomerAddressRequest|UpdateCustomerAddressRequest $request): self
    {
        return new self(
            label: $request->string('label')->toString(),
            recipientName: $request->string('recipient_name')->toString(),
            whatsappPhone: $request->string('whatsapp_phone')->toString(),
            address: $request->string('address')->toString(),
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
            latitude: $request->float('latitude'),
            longitude: $request->float('longitude'),
            isDefault: $request->boolean('is_default'),
        );
    }

    /** @return array<string, bool|float|string|null> */
    public function attributes(): array
    {
        return [
            'label' => $this->label,
            'recipient_name' => $this->recipientName,
            'whatsapp_phone' => $this->whatsappPhone,
            'address' => $this->address,
            'notes' => $this->notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->isDefault,
        ];
    }
}
