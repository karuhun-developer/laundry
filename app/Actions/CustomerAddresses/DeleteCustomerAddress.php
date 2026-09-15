<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerAddress
{
    public function handle(User $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $lockedAddress = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->whereKey($address->id)
                ->lockForUpdate()
                ->firstOrFail();
            $wasDefault = $lockedAddress->is_default;

            activity('customer_addresses')
                ->causedBy($customer)
                ->performedOn($lockedAddress)
                ->withProperties(['customer_id' => $customer->id])
                ->log('customer address deleted');

            $lockedAddress->delete();

            if ($wasDefault) {
                $customer->customerAddresses()
                    ->oldest('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
