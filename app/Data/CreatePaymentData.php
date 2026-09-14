<?php

namespace App\Data;

use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use LogicException;

final readonly class CreatePaymentData
{
    public function __construct(
        public PaymentMethod $method,
        public int $amountRupiah,
        public ?string $note,
        public ?PaymentGatewayDriver $gatewayDriver,
        public ?string $gatewayMethod,
    ) {}

    public static function fromRequest(StorePaymentRequest $request): self
    {
        $method = $request->validated('method');
        $note = $request->validated('note');
        $gatewayDriver = $request->validated('gateway_driver');
        $gatewayMethod = $request->validated('gateway_method');

        if (
            ! is_string($method)
            || ($note !== null && ! is_string($note))
            || ($gatewayDriver !== null && ! is_string($gatewayDriver))
            || ($gatewayMethod !== null && ! is_string($gatewayMethod))
        ) {
            throw new LogicException('Validated payment input has an unexpected type.');
        }

        $normalizedNote = $note === null ? null : trim($note);

        return new self(
            method: PaymentMethod::from($method),
            amountRupiah: $request->integer('amount_rupiah'),
            note: $normalizedNote === '' ? null : $normalizedNote,
            gatewayDriver: $gatewayDriver === null
                ? null
                : PaymentGatewayDriver::from($gatewayDriver),
            gatewayMethod: $gatewayMethod,
        );
    }
}
