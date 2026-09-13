<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ListOrganizationCustomers
{
    /** @return Collection<int, User> */
    public function handle(Organization $organization): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query
                ->where('name', RoleName::Customer->value))
            ->where(fn (Builder $query): Builder => $query
                ->where('organization_id', $organization->id)
                ->orWhere(fn (Builder $neutralCustomerQuery): Builder => $neutralCustomerQuery
                    ->whereNull('organization_id')
                    ->whereHas('customerOrders', fn (Builder $orderQuery): Builder => $orderQuery
                        ->where('organization_id', $organization->id))))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
