<?php

namespace App\Actions\Customers;

use App\Data\UpdateCustomerData;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class UpdateCustomer
{
    public function handle(
        Organization $organization,
        User $actor,
        User $customer,
        UpdateCustomerData $data,
    ): User {
        return DB::transaction(function () use ($organization, $actor, $customer, $data): User {
            $lockedCustomer = User::query()
                ->customerOnly()
                ->whereKey($customer->id)
                ->whereHas('customerOrganizations', fn (Builder $query): Builder => $query
                    ->whereKey($organization->id))
                ->has('customerOrganizations', '=', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCustomer->fill($data->attributes());
            $changedFields = array_keys($lockedCustomer->getDirty());
            $lockedCustomer->save();

            activity('customers')
                ->causedBy($actor)
                ->performedOn($lockedCustomer)
                ->withProperties([
                    'customer_id' => $lockedCustomer->id,
                    'organization_id' => $organization->id,
                    'changed_fields' => $changedFields,
                ])
                ->log('customer updated');

            return $lockedCustomer->refresh();
        });
    }
}
