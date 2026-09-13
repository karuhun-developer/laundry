<?php

namespace App\Actions\Orders;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final class ListMobileOrders
{
    /** @return Collection<int, Order> */
    public function handle(Organization $organization, User $user): Collection
    {
        $query = Order::query()
            ->where('organization_id', $organization->id)
            ->with(['branch', 'items', 'statusEvents']);

        if ($user->hasRole(RoleName::Customer->value)) {
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(RoleName::BranchAdmin->value)) {
            $query->whereIn('branch_id', $user->branches()->select('branches.id'));
        } else {
            throw new AuthorizationException;
        }

        return $query->latest('id')->limit(100)->get();
    }
}
