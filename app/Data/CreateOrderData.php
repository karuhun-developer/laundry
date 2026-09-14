<?php

namespace App\Data;

use App\Enums\RoleName;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Models\User;
use LogicException;

final readonly class CreateOrderData
{
    /** @param list<CreateOrderItemData> $items */
    public function __construct(
        public int $customerId,
        public array $items,
        public ?float $customerLatitude,
        public ?float $customerLongitude,
    ) {}

    public static function fromRequest(StoreOrderRequest $request): self
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to create an order.');
        }

        /** @var list<array{price_option_id: int, weight_grams?: int}> $validatedItems */
        $validatedItems = $request->validated('items');
        $items = array_map(
            static fn (array $item): CreateOrderItemData => new CreateOrderItemData(
                priceOptionId: $item['price_option_id'],
                weightGrams: $item['weight_grams'] ?? null,
            ),
            $validatedItems,
        );
        $hasDelivery = $request->filled('delivery.latitude')
            && $request->filled('delivery.longitude');

        return new self(
            customerId: $actor->hasRole(RoleName::Customer->value)
                ? $actor->id
                : $request->integer('customer_id'),
            items: $items,
            customerLatitude: $hasDelivery ? $request->float('delivery.latitude') : null,
            customerLongitude: $hasDelivery ? $request->float('delivery.longitude') : null,
        );
    }
}
