<?php

namespace App\Actions\Customers;

use App\Data\CustomerCredentialData;
use App\Data\StoreCustomerData;
use App\Data\StoreCustomerResult;
use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class CreateOrAttachCustomer
{
    public function handle(Organization $organization, User $actor, StoreCustomerData $data): StoreCustomerResult
    {
        return DB::transaction(function () use ($organization, $actor, $data): StoreCustomerResult {
            $temporaryPin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addDay();
            $customer = User::query()->createOrFirst(
                ['whatsapp_phone' => $data->whatsappPhone],
                [
                    'organization_id' => null,
                    'name' => $data->name,
                    'email' => null,
                    'password' => null,
                    'pin_hash' => Hash::make($temporaryPin),
                    'pin_expires_at' => $expiresAt,
                    'must_change_pin' => true,
                    'session_version' => 1,
                ],
            );
            $wasRecentlyCreated = $customer->wasRecentlyCreated;
            $customer = User::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $wasRecentlyCreated) {
                $this->ensureCustomerOnlyAccount($customer);
                $customer->customerOrganizations()->syncWithoutDetaching([$organization->id]);

                activity('customers')
                    ->causedBy($actor)
                    ->performedOn($customer)
                    ->withProperties([
                        'customer_id' => $customer->id,
                        'organization_id' => $organization->id,
                    ])
                    ->log('customer attached');

                return new StoreCustomerResult(
                    customer: $customer->refresh(),
                    credential: null,
                );
            }

            $customer->assignRole(RoleName::Customer->value);
            $customer->customerOrganizations()->attach($organization);

            activity('customers')
                ->causedBy($actor)
                ->performedOn($customer)
                ->withProperties([
                    'customer_id' => $customer->id,
                    'organization_id' => $organization->id,
                    'temporary_pin_expires_at' => $expiresAt->toIso8601String(),
                ])
                ->log('customer created');

            $credential = new CustomerCredentialData(
                customer: $customer->refresh(),
                temporaryPin: $temporaryPin,
                expiresAt: $expiresAt,
            );

            return new StoreCustomerResult(
                customer: $credential->customer,
                credential: $credential,
            );
        }, attempts: 3);
    }

    private function ensureCustomerOnlyAccount(User $account): void
    {
        $hasCustomerRole = $account->hasRole(RoleName::Customer->value);
        $hasNonCustomerRole = $account->roles()
            ->where('name', '!=', RoleName::Customer->value)
            ->exists();

        if (! $hasCustomerRole || $hasNonCustomerRole) {
            throw ValidationException::withMessages([
                'whatsapp_phone' => 'Nomor WhatsApp sudah digunakan oleh akun non-customer.',
            ]);
        }
    }
}
