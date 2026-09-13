<?php

namespace App\Services;

class WhatsAppPhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return preg_match('/^628[1-9][0-9]{7,11}$/', $digits) === 1 ? $digits : null;
    }
}
