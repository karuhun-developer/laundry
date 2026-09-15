<?php

namespace App\Rules;

use App\Exceptions\InvalidQrisPayload;
use App\Services\Qris\QrisPayloadGenerator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class ValidQrisPayload implements ValidationRule
{
    public function __construct(
        private readonly QrisPayloadGenerator $generator = new QrisPayloadGenerator,
    ) {}

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $this->generator->generate($value, 1);
        } catch (InvalidQrisPayload $exception) {
            $fail($exception->getMessage());
        }
    }
}
