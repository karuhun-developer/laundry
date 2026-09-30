<?php

namespace App\Actions\Reports;

use App\Enums\CashflowType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CashflowEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class BuildDailySummary
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{date: string, timezone: string, receipts: array<string, int>, manual_income_rupiah: int, manual_expenses_rupiah: int, net_income_rupiah: int, outstanding_rupiah: int, statistics: array{total: int, active: int, unweighed: int, ready: int, overdue: int, tasks: int, complaints: int}}
     */
    public function handle(User $user, array $filters): array
    {
        $branches = Branch::query();
        if (! $user->hasRole(RoleName::Superadmin->value)) {
            $branches->where('organization_id', $user->organization_id);
        } elseif (! empty($filters['organization_id'])) {
            $branches->where('organization_id', $filters['organization_id']);
        }
        if ($user->hasRole(RoleName::BranchAdmin->value)) {
            $branches->whereIn('id', $user->branches()->select('branches.id'));
        }
        if (! empty($filters['branch_id'])) {
            $branches->whereKey($filters['branch_id']);
        }
        $branchIds = $branches->pluck('id');
        $date = Carbon::parse($filters['date'] ?? now()->toDateString(), config('app.timezone'));
        $orders = Order::query()->whereIn('branch_id', $branchIds);
        $payments = Payment::query()->whereIn('branch_id', $branchIds)->where('status', PaymentStatus::Paid)
            ->whereBetween('settled_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        $receipts = [];
        foreach (PaymentMethod::cases() as $method) {
            $receipts[$method->value] = (int) (clone $payments)->where('method', $method)->sum('amount_rupiah');
        }
        $manual = CashflowEntry::query()->whereIn('branch_id', $branchIds)->whereNull('payment_id')->whereDate('entry_date', $date->toDateString());
        $income = (int) (clone $manual)->where('type', CashflowType::Debit)->sum('amount_rupiah');
        $expense = (int) (clone $manual)->where('type', CashflowType::Credit)->sum('amount_rupiah');
        $outstanding = (clone $orders)->whereNotNull('priced_at')->where('status', '!=', OrderStatus::Cancelled)
            ->selectRaw('coalesce(sum(case when total_rupiah > (select coalesce(sum(amount_rupiah), 0) from payments where payments.order_id = orders.id and payments.status = ?) then total_rupiah - (select coalesce(sum(amount_rupiah), 0) from payments where payments.order_id = orders.id and payments.status = ?) else 0 end), 0) as outstanding', [PaymentStatus::Paid->value, PaymentStatus::Paid->value])->value('outstanding');

        return ['date' => $date->toDateString(), 'timezone' => config('app.timezone'), 'receipts' => $receipts,
            'manual_income_rupiah' => $income, 'manual_expenses_rupiah' => $expense,
            'net_income_rupiah' => array_sum($receipts) + $income - $expense, 'outstanding_rupiah' => (int) $outstanding,
            'statistics' => ['total' => (clone $orders)->count(),
                'active' => (clone $orders)->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count(),
                'unweighed' => (clone $orders)->whereNull('priced_at')->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count(),
                'ready' => (clone $orders)->where('status', OrderStatus::ReadyForPickup)->count(),
                'overdue' => (clone $orders)->where('estimated_ready_at', '<', now())->whereNotIn('status', [OrderStatus::ReadyForPickup, OrderStatus::Completed, OrderStatus::Cancelled])->count(),
                'tasks' => (clone $orders)->whereHas('tasks', fn (Builder $query): Builder => $query->whereNotIn('status', ['completed', 'cancelled']))->count(),
                'complaints' => (clone $orders)->whereHas('complaints', fn (Builder $query): Builder => $query->where('status', '!=', 'resolved'))->count(),
            ]];
    }
}
