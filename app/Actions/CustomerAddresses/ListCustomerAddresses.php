<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListCustomerAddresses
{
    /** @return Collection<int, CustomerAddress> */
    public function handle(User $customer): Collection
    {
        return $customer->customerAddresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();
    }
}
