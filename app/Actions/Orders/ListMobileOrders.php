<?php

namespace App\Actions\Orders;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ListMobileOrders
{
    /** @return Collection<int, Order> */
    public function handle(Organization $organization, User $user): Collection
    {
        $query = $this->query()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->limit(100);

        if ($user->hasRole(RoleName::Customer->value)) {
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(RoleName::BranchAdmin->value)) {
            $query->whereIn('branch_id', $user->branches()->select('branches.id'));
        } else {
            throw new AuthorizationException;
        }

        return $query->get();
    }

    /** @return Collection<int, Order> */
    public function handleForCustomer(User $user): Collection
    {
        if (! $user->hasRole(RoleName::Customer->value)) {
            throw new AuthorizationException;
        }

        return $this->query()
            ->where('customer_id', $user->id)
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** @return Builder<Order> */
    private function query(): Builder
    {
        return Order::query()->with([
            'organization:id,slug',
            'branch',
            'items',
            'statusEvents',
            'payments.statusEvents',
        ]);
    }
}
