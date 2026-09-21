<?php

namespace App\Actions\Auth;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AuthenticateCustomer
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    public function __construct(
        private readonly WhatsAppPhoneNormalizer $phoneNormalizer = new WhatsAppPhoneNormalizer,
    ) {}

    public function handle(string $whatsappPhone, string $pin, string $throttleKey): User
    {
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'whatsapp_phone' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ])->status(429);
        }

        $normalizedPhone = $this->phoneNormalizer->normalize($whatsappPhone) ?? $whatsappPhone;
        $rawDigits = preg_replace('/\D+/', '', $whatsappPhone) ?: $whatsappPhone;
        $localPhone = str_starts_with($rawDigits, '62') ? '0'.substr($rawDigits, 2) : $rawDigits;

        $customer = User::query()
            ->where(function ($query) use ($whatsappPhone, $normalizedPhone, $rawDigits, $localPhone): void {
                $query->where('whatsapp_phone', $normalizedPhone)
                    ->orWhere('whatsapp_phone', $whatsappPhone)
                    ->orWhere('whatsapp_phone', $rawDigits)
                    ->orWhere('whatsapp_phone', $localPhone);
            })
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::Customer->value))
            ->first();

        if (! $customer instanceof User || ! $customer->customerPinMatches($pin)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'whatsapp_phone' => 'Nomor WhatsApp atau PIN tidak valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $customer;
    }
}
