<?php

namespace App\Actions\Reports;

use App\Data\Reports\OperationsReportData;
use App\Data\Reports\ReportFiltersData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BuildOperationsReport
{
    public function handle(User $user, ReportFiltersData $filters): OperationsReportData
    {
        $this->authorize($user, $filters);

        $allowedOrganizationId = $user->hasRole(RoleName::Owner->value)
            ? $user->organization_id
            : null;
        $optionBranches = Branch::query()
            ->with(['city:id,name,province', 'organization:id,name'])
            ->when(
                $allowedOrganizationId !== null,
                fn (Builder $query) => $query->where('organization_id', $allowedOrganizationId),
            )
            ->orderBy('name')
            ->get();
        $reportBranches = $optionBranches
            ->when(
                $filters->organizationId !== null,
                fn (Collection $branches): Collection => $branches
                    ->where('organization_id', $filters->organizationId),
            )
            ->when(
                $filters->cityId !== null,
                fn (Collection $branches): Collection => $branches->where('city_id', $filters->cityId),
            )
            ->when(
                $filters->branchId !== null,
                fn (Collection $branches): Collection => $branches->where('id', $filters->branchId),
            )
            ->values();
        $reportBranchIds = $reportBranches->pluck('id');

        $orders = Order::query()
            ->select(['id', 'branch_id', 'status', 'created_at'])
            ->whereBetween('created_at', [$filters->dateFrom, $filters->dateTo])
            ->whereIn('branch_id', $reportBranchIds)
            ->when(
                $filters->serviceId !== null,
                fn (Builder $query) => $query->whereHas(
                    'items',
                    fn (Builder $itemQuery) => $itemQuery->where('service_id', $filters->serviceId),
                ),
            )
            ->when(
                $filters->status !== null,
                fn (Builder $query) => $query->where('status', $filters->status->value),
            )
            ->get();
        $orderIds = $orders->pluck('id');

        $paidRevenueByBranch = $orderIds->isEmpty()
            ? collect()
            : Payment::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', PaymentStatus::Paid->value)
                ->selectRaw('branch_id, SUM(amount_rupiah) AS paid_revenue_rupiah')
                ->groupBy('branch_id')
                ->pluck('paid_revenue_rupiah', 'branch_id');
        $completedAtByOrder = $orderIds->isEmpty()
            ? collect()
            : OrderStatusEvent::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', OrderStatus::Completed->value)
                ->selectRaw('order_id, MIN(created_at) AS completed_at')
                ->groupBy('order_id')
                ->pluck('completed_at', 'order_id');
        $ordersByBranch = $orders->groupBy('branch_id');

        $branchComparisons = $reportBranches
            ->map(function (Branch $branch) use ($ordersByBranch, $paidRevenueByBranch, $completedAtByOrder): array {
                /** @var Collection<int, Order> $branchOrders */
                $branchOrders = $ordersByBranch->get($branch->id, collect());
                $orderCount = $branchOrders->count();
                $cancelledOrderCount = $branchOrders
                    ->where('status', OrderStatus::Cancelled)
                    ->count();

                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_code' => $branch->code,
                    'organization_name' => $branch->organization->name,
                    'city_name' => $branch->city->name,
                    'order_count' => $orderCount,
                    'paid_revenue_rupiah' => (int) $paidRevenueByBranch->get($branch->id, 0),
                    'completed_order_count' => $branchOrders
                        ->where('status', OrderStatus::Completed)
                        ->count(),
                    'cancelled_order_count' => $cancelledOrderCount,
                    'cancellation_rate' => $this->cancellationRate($cancelledOrderCount, $orderCount),
                    'average_turnaround_minutes' => $this->averageTurnaroundMinutes(
                        $branchOrders,
                        $completedAtByOrder,
                    ),
                ];
            })
            ->values()
            ->all();
        $cancelledOrderCount = $orders->where('status', OrderStatus::Cancelled)->count();
        $services = Service::query()
            ->when(
                $allowedOrganizationId !== null,
                fn (Builder $query) => $query->where('organization_id', $allowedOrganizationId),
            )
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'code']);

        return new OperationsReportData(
            filters: $filters,
            paidRevenueRupiah: (int) $paidRevenueByBranch->sum(),
            orderCount: $orders->count(),
            averageTurnaroundMinutes: $this->averageTurnaroundMinutes($orders, $completedAtByOrder),
            cancelledOrderCount: $cancelledOrderCount,
            cancellationRate: $this->cancellationRate($cancelledOrderCount, $orders->count()),
            branchComparisons: $branchComparisons,
            organizations: $user->hasRole(RoleName::Superadmin->value)
                ? Organization::query()->orderBy('name')->get(['id', 'name'])->toArray()
                : [],
            cities: City::query()
                ->orderBy('name')
                ->get(['id', 'name', 'province'])
                ->toArray(),
            branches: $optionBranches
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'organization_id' => $branch->organization_id,
                    'city_id' => $branch->city_id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ])
                ->values()
                ->all(),
            services: $services
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'organization_id' => $service->organization_id,
                    'name' => $service->name,
                    'code' => $service->code,
                ])
                ->values()
                ->all(),
            statuses: array_map(
                static fn (OrderStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                OrderStatus::cases(),
            ),
            defaultDateFrom: now()->subDays(29)->toDateString(),
            defaultDateTo: now()->toDateString(),
        );
    }

    private function authorize(User $user, ReportFiltersData $filters): void
    {
        if ($user->hasRole(RoleName::Superadmin->value)) {
            return;
        }

        if (! $user->hasRole(RoleName::Owner->value)
            || $user->organization_id === null
            || $filters->organizationId !== $user->organization_id) {
            throw new AuthorizationException;
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, mixed>  $completedAtByOrder
     */
    private function averageTurnaroundMinutes(
        Collection $orders,
        Collection $completedAtByOrder,
    ): ?int {
        $durations = $orders
            ->map(function (Order $order) use ($completedAtByOrder): ?int {
                $completedAt = $completedAtByOrder->get($order->id);

                if (! is_string($completedAt)) {
                    return null;
                }

                $minutes = $order->created_at->diffInMinutes(
                    CarbonImmutable::parse($completedAt),
                    false,
                );

                return $minutes >= 0 ? (int) round($minutes) : null;
            })
            ->filter(static fn (?int $minutes): bool => $minutes !== null);

        return $durations->isEmpty()
            ? null
            : (int) round($durations->average());
    }

    private function cancellationRate(int $cancelledOrderCount, int $orderCount): float
    {
        return $orderCount === 0
            ? 0.0
            : round(($cancelledOrderCount / $orderCount) * 100, 1);
    }
}
