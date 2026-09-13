<?php

namespace App\Data;

use App\Enums\PaymentStatus;
use App\Http\Requests\Api\V1\StorePaymentStatusEventRequest;
use LogicException;

final readonly class TransitionPaymentStatusData
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $note,
    ) {}

    public static function fromRequest(StorePaymentStatusEventRequest $request): self
    {
        $status = $request->validated('status');
        $note = $request->validated('note');

        if (! is_string($status) || ($note !== null && ! is_string($note))) {
            throw new LogicException('Validated payment status input has an unexpected type.');
        }

        $normalizedNote = $note === null ? null : trim($note);

        return new self(
            status: PaymentStatus::from($status),
            note: $normalizedNote === '' ? null : $normalizedNote,
        );
    }
}
