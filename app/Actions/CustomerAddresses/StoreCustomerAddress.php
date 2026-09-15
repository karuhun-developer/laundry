<?php

namespace App\Actions\CustomerAddresses;

use App\Data\CustomerAddressData;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreCustomerAddress
{
    public function handle(User $customer, CustomerAddressData $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $lockedCustomer = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $shouldBeDefault = $data->isDefault || ! $lockedCustomer->customerAddresses()->exists();

            if ($shouldBeDefault) {
                $lockedCustomer->customerAddresses()->update(['is_default' => false]);
            }

            $address = $lockedCustomer->customerAddresses()->create([
                ...$data->attributes(),
                'is_default' => $shouldBeDefault,
            ]);

            activity('customer_addresses')
                ->causedBy($customer)
                ->performedOn($address)
                ->withProperties(['customer_id' => $customer->id])
                ->log('customer address created');

            return $address;
        });
    }
}
