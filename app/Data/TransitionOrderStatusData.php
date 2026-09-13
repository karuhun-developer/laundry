<?php

namespace App\Data;

use App\Enums\OrderStatus;
use App\Http\Requests\Api\V1\StoreOrderStatusEventRequest;
use LogicException;

final readonly class TransitionOrderStatusData
{
    public function __construct(
        public OrderStatus $status,
        public ?string $note,
    ) {}

    public static function fromRequest(StoreOrderStatusEventRequest $request): self
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? null;
        $note = $validated['note'] ?? null;

        if (! is_string($status) || ($note !== null && ! is_string($note))) {
            throw new LogicException('Validated order status input has an unexpected type.');
        }

        $normalizedNote = $note === null ? null : trim($note);

        return new self(
            status: OrderStatus::from($status),
            note: $normalizedNote === '' ? null : $normalizedNote,
        );
    }
}
