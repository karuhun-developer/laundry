<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SetDefaultCustomerAddress
{
    public function handle(User $customer, CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address): CustomerAddress {
            $lockedAddress = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->whereKey($address->id)
                ->lockForUpdate()
                ->firstOrFail();

            $customer->customerAddresses()->whereKeyNot($lockedAddress->id)->update(['is_default' => false]);
            $lockedAddress->update(['is_default' => true]);

            activity('customer_addresses')
                ->causedBy($customer)
                ->performedOn($lockedAddress)
                ->withProperties(['customer_id' => $customer->id])
                ->log('customer address made default');

            return $lockedAddress->refresh();
        });
    }
}
