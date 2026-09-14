<?php

namespace App\Actions\Customers;

use App\Actions\Auth\InvalidateCustomerSessions;
use App\Data\CustomerCredentialData;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ResetCustomerPin
{
    public function __construct(private InvalidateCustomerSessions $invalidateCustomerSessions) {}

    public function handle(Organization $organization, User $actor, User $customer): CustomerCredentialData
    {
        return DB::transaction(function () use ($organization, $actor, $customer): CustomerCredentialData {
            $lockedCustomer = User::query()
                ->customerOnly()
                ->whereKey($customer->id)
                ->whereHas('customerOrganizations', fn (Builder $query): Builder => $query
                    ->whereKey($organization->id))
                ->has('customerOrganizations', '=', 1)
                ->lockForUpdate()
                ->firstOrFail();
            $temporaryPin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addDay();

            $lockedCustomer->setCustomerPin($temporaryPin, $expiresAt, true);
            $lockedCustomer->save();
            $this->invalidateCustomerSessions->handle($lockedCustomer);

            activity('customers')
                ->causedBy($actor)
                ->performedOn($lockedCustomer)
                ->withProperties([
                    'customer_id' => $lockedCustomer->id,
                    'organization_id' => $organization->id,
                    'temporary_pin_expires_at' => $expiresAt->toIso8601String(),
                    'sessions_invalidated' => true,
                ])
                ->log('customer pin reset');

            return new CustomerCredentialData(
                customer: $lockedCustomer->refresh(),
                temporaryPin: $temporaryPin,
                expiresAt: $expiresAt,
            );
        });
    }
}
