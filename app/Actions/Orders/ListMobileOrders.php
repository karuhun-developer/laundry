<?php

namespace App\Actions\Orders;

use App\Data\ListMobileOrdersData;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ListMobileOrders
{
    public function __construct(private FilterOrderQueue $filters) {}

    /** @return Collection<int, Order>|LengthAwarePaginator<int, Order> */
    public function handle(
        Organization $organization,
        User $user,
        ListMobileOrdersData $data,
    ): Collection|LengthAwarePaginator {
        $query = $this->query()
            ->where('organization_id', $organization->id)
            ->latest('id');

        if ($user->hasRole(RoleName::Customer->value)) {
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(RoleName::BranchAdmin->value)) {
            $query->whereIn('branch_id', $user->branches()->select('branches.id'));
        } else {
            throw new AuthorizationException;
        }

        $this->filters->handle($query, [...$data->filters, 'search' => $data->search], $user->hasRole(RoleName::Customer->value));

        return ! empty($data->filters['paginated']) ? $query->paginate(25) : $query->limit(100)->get();
    }

    /**
     * @param  array{search?: string, date_from?: string, date_to?: string, paginated?: bool, page?: int, branch_id?: int, status?: string, queue?: string, unpaid?: bool, overdue?: bool}  $filters
     * @return Collection<int, Order>|LengthAwarePaginator<int, Order>
     */
    public function handleForCustomer(User $user, array $filters = []): Collection|LengthAwarePaginator
    {
        if (! $user->hasRole(RoleName::Customer->value)) {
            throw new AuthorizationException;
        }

        $query = $this->query()
            ->where('customer_id', $user->id)
            ->latest('id');

        $this->filters->handle($query, $filters, true);

        return ! empty($filters['paginated']) ? $query->paginate(25) : $query->limit(100)->get();
    }

    /** @return Builder<Order> */
    private function query(): Builder
    {
        return Order::query()->with([
            'organization:id,name,slug',
            'branch',
            'items',
            'statusEvents',
            'payments.statusEvents',
            'tasks.events', 'tasks.media', 'complaints.events', 'complaints.media',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, active: int, in_progress: int, unweighed: int, ready: int, completed: int, overdue: int}
     */
    public function statistics(User $user, array $filters, ?Organization $organization = null): array
    {
        $query = Order::query();
        if ($organization !== null) {
            $query->where('organization_id', $organization->id);
        }
        if ($user->hasRole(RoleName::Customer->value)) {
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(RoleName::BranchAdmin->value)) {
            $query->whereIn('branch_id', $user->branches()->select('branches.id'));
        } else {
            throw new AuthorizationException;
        }
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $this->filters->statistics($query);
    }
}
