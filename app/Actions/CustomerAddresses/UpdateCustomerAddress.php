<?php

namespace App\Actions\CustomerAddresses;

use App\Data\CustomerAddressData;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerAddress
{
    public function handle(User $customer, CustomerAddress $address, CustomerAddressData $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $data): CustomerAddress {
            $lockedAddress = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->whereKey($address->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($data->isDefault) {
                $customer->customerAddresses()
                    ->whereKeyNot($lockedAddress->id)
                    ->update(['is_default' => false]);
            }

            $attributes = $data->attributes();

            if ($lockedAddress->is_default && ! $data->isDefault) {
                $attributes['is_default'] = true;
            }

            $lockedAddress->fill($attributes);
            $changedFields = array_keys($lockedAddress->getDirty());
            $lockedAddress->save();

            activity('customer_addresses')
                ->causedBy($customer)
                ->performedOn($lockedAddress)
                ->withProperties([
                    'customer_id' => $customer->id,
                    'changed_fields' => $changedFields,
                ])
                ->log('customer address updated');

            return $lockedAddress->refresh();
        });
    }
}
