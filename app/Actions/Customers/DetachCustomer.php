<?php

namespace App\Actions\Customers;

use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DetachCustomer
{
    public function handle(Organization $organization, User $actor, User $customer): void
    {
        DB::transaction(function () use ($organization, $actor, $customer): void {
            $lockedCustomer = User::query()
                ->customerOnly()
                ->whereKey($customer->id)
                ->whereHas('customerOrganizations', fn (Builder $query): Builder => $query
                    ->whereKey($organization->id))
                ->lockForUpdate()
                ->firstOrFail();

            $hasOrderHistory = Order::query()
                ->where('organization_id', $organization->id)
                ->where('customer_id', $lockedCustomer->id)
                ->exists();

            if ($hasOrderHistory) {
                throw ValidationException::withMessages([
                    'customer' => 'Customer dengan riwayat pesanan tidak dapat dilepas dari organisasi.',
                ]);
            }

            $lockedCustomer->customerOrganizations()->detach($organization->id);

            activity('customers')
                ->causedBy($actor)
                ->performedOn($lockedCustomer)
                ->withProperties([
                    'customer_id' => $lockedCustomer->id,
                    'organization_id' => $organization->id,
                ])
                ->log('customer detached');
        });
    }
}
