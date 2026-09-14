<?php

namespace App\Actions\Auth;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerPin
{
    public function __construct(
        private readonly InvalidateCustomerSessions $invalidateCustomerSessions,
    ) {}

    public function handle(
        User $customer,
        string $currentPin,
        string $pin,
        ?string $currentSessionId = null,
    ): User {
        if (! $customer->hasRole(RoleName::Customer->value) || ! $customer->customerPinMatches($currentPin)) {
            throw ValidationException::withMessages([
                'current_pin' => 'PIN saat ini tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($customer, $pin, $currentSessionId): User {
            $customer->setCustomerPin($pin);
            $customer->save();

            $this->invalidateCustomerSessions->handle($customer, $currentSessionId);

            return $customer;
        });
    }
}
