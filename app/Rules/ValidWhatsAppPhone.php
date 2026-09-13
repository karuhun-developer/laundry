<?php

namespace App\Rules;

use App\Services\WhatsAppPhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidWhatsAppPhone implements ValidationRule
{
    public function __construct(
        private readonly WhatsAppPhoneNormalizer $normalizer = new WhatsAppPhoneNormalizer,
    ) {}

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->normalizer->normalize($value) === null) {
            $fail('Nomor WhatsApp harus berupa nomor seluler Indonesia yang valid.');
        }
    }
}
