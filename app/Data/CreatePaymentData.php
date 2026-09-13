<?php

namespace App\Data;

use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use LogicException;

final readonly class CreatePaymentData
{
    public function __construct(
        public PaymentMethod $method,
        public int $amountRupiah,
        public ?string $note,
    ) {}

    public static function fromRequest(StorePaymentRequest $request): self
    {
        $method = $request->validated('method');
        $note = $request->validated('note');

        if (! is_string($method) || ($note !== null && ! is_string($note))) {
            throw new LogicException('Validated payment input has an unexpected type.');
        }

        $normalizedNote = $note === null ? null : trim($note);

        return new self(
            method: PaymentMethod::from($method),
            amountRupiah: $request->integer('amount_rupiah'),
            note: $normalizedNote === '' ? null : $normalizedNote,
        );
    }
}
