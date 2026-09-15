<?php

namespace App\Actions\Orders;

use App\Data\ListMobileOrdersData;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ListMobileOrders
{
    /** @return Collection<int, Order> */
    public function handle(
        Organization $organization,
        User $user,
        ListMobileOrdersData $data,
    ): Collection {
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

        if ($data->search !== null) {
            $term = $data->search;
            $phone = preg_replace('/\D+/', '', $term) ?? '';

            if (str_starts_with($phone, '0')) {
                $phone = '62'.substr($phone, 1);
            }

            $query->where(function (Builder $searchQuery) use ($term, $phone): void {
                $searchQuery
                    ->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name_snapshot', 'like', "%{$term}%");

                if ($phone !== '') {
                    $searchQuery->orWhere('customer_whatsapp_phone_snapshot', 'like', "%{$phone}%");
                }
            });
        }

        return $query->get();
    }

    /**
     * @param  array{search?: string, date_from?: string, date_to?: string}  $filters
     * @return Collection<int, Order>
     */
    public function handleForCustomer(User $user, array $filters = []): Collection
    {
        if (! $user->hasRole(RoleName::Customer->value)) {
            throw new AuthorizationException;
        }

        $query = $this->query()
            ->where('customer_id', $user->id)
            ->latest('id')
            ->limit(100);

        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('branch', function (Builder $branchQuery) use ($search): void {
                        $branchQuery->where(function (Builder $branchSearchQuery) use ($search): void {
                            $branchSearchQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->orWhereHas('items', function (Builder $itemQuery) use ($search): void {
                        $itemQuery->where(function (Builder $itemSearchQuery) use ($search): void {
                            $itemSearchQuery
                                ->where('service_name_snapshot', 'like', "%{$search}%")
                                ->orWhere('service_code_snapshot', 'like', "%{$search}%");
                        });
                    });
            });
        }

        if (isset($filters['date_from'])) {
            $query->where(
                'created_at',
                '>=',
                Carbon::parse($filters['date_from'])->startOfDay(),
            );
        }

        if (isset($filters['date_to'])) {
            $query->where(
                'created_at',
                '<=',
                Carbon::parse($filters['date_to'])->endOfDay(),
            );
        }

        return $query->get();
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
