<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SupportedMessageTemplateVariables implements ValidationRule
{
    /** @var list<string> */
    public const VARIABLES = [
        'customer_name',
        'name',
        'order_number',
        'status',
        'branch_name',
        'weight',
        'total',
        'tracking_url',
    ];

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        preg_match_all('/\{([^{}]+)\}/u', $value, $matches);
        $variables = array_values(array_unique($matches[1]));
        $unsupported = array_values(array_diff($variables, self::VARIABLES));

        if ($unsupported !== []) {
            $fail('Variabel template tidak didukung: '.implode(', ', array_map(
                static fn (string $variable): string => '{'.$variable.'}',
                $unsupported,
            )).'.');

            return;
        }

        $withoutVariables = preg_replace('/\{[^{}]+\}/u', '', $value);

        if ($withoutVariables === null
            || str_contains($withoutVariables, '{')
            || str_contains($withoutVariables, '}')) {
            $fail('Format variabel template tidak valid. Gunakan format seperti {customer_name}.');
        }
    }
}
