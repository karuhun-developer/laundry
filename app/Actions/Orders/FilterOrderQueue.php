<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class FilterOrderQueue
{
    /**
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    public function handle(Builder $query, array $filters, bool $customer = false): Builder
    {
        $search = trim($filters['search'] ?? '');
        foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $search) {
            $query->where(function (Builder $searchQuery) use ($search, $customer): void {
                $searchQuery->where('order_number', 'like', "%{$search}%");
                if ($customer) {
                    $searchQuery->orWhereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery
                        ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn (Builder $itemQuery): Builder => $itemQuery
                            ->where('service_name_snapshot', 'like', "%{$search}%")
                            ->orWhere('service_code_snapshot', 'like', "%{$search}%"));
                } else {
                    $searchQuery->orWhere('customer_name_snapshot', 'like', "%{$search}%");
                    $phone = preg_replace('/\D+/', '', $search) ?? '';
                    if (str_starts_with($phone, '0')) {
                        $phone = '62'.substr($phone, 1);
                    }
                    if ($phone !== '') {
                        $searchQuery->orWhere('customer_whatsapp_phone_snapshot', 'like', "%{$phone}%");
                    }
                }
            });
        }
        foreach (['branch_id', 'organization_id', 'status'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay());
        }
        $queue = $filters['queue'] ?? 'all';
        if ($queue === 'active') {
            $query->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled]);
        } elseif ($queue === 'history') {
            $query->whereIn('status', [OrderStatus::Completed, OrderStatus::Cancelled]);
        } elseif ($queue === 'unweighed') {
            $query->whereNull('priced_at')->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled]);
        } elseif ($queue === 'ready') {
            $query->where('status', OrderStatus::ReadyForPickup);
        }
        if (! empty($filters['overdue']) || $queue === 'overdue') {
            $query->where('estimated_ready_at', '<', now())
                ->whereNotIn('status', [OrderStatus::ReadyForPickup, OrderStatus::Completed, OrderStatus::Cancelled]);
        }
        if (! empty($filters['unpaid'])) {
            $query->whereNotNull('priced_at')->where('status', '!=', OrderStatus::Cancelled)
                ->whereRaw('total_rupiah > (select coalesce(sum(amount_rupiah), 0) from payments where payments.order_id = orders.id and payments.status = ?)', [PaymentStatus::Paid->value]);
        }

        return $query;
    }

    /**
     * @param  Builder<Order>  $query
     * @return array{total: int, active: int, in_progress: int, unweighed: int, ready: int, completed: int, overdue: int}
     */
    public function statistics(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count(),
            'in_progress' => (clone $query)->whereNotIn('status', [OrderStatus::ReadyForPickup, OrderStatus::Completed, OrderStatus::Cancelled])->count(),
            'unweighed' => (clone $query)->whereNull('priced_at')->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count(),
            'ready' => (clone $query)->where('status', OrderStatus::ReadyForPickup)->count(),
            'completed' => (clone $query)->where('status', OrderStatus::Completed)->count(),
            'overdue' => (clone $query)->where('estimated_ready_at', '<', now())->whereNotIn('status', [OrderStatus::ReadyForPickup, OrderStatus::Completed, OrderStatus::Cancelled])->count(),
        ];
    }
}
