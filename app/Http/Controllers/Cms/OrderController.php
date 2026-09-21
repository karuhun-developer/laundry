<?php

namespace App\Http\Controllers\Cms;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Order::class);

        /** @var User $user */
        $user = $request->user();
        $isSuperadmin = $user->hasRole(RoleName::Superadmin->value);
        $isBranchAdmin = $user->hasRole(RoleName::BranchAdmin->value);
        $branchIds = $isBranchAdmin ? $user->branches()->pluck('branches.id') : null;

        $orders = Order::query()
            ->with([
                'organization:id,name,slug',
                'branch:id,organization_id,name,code,qris_enabled',
                'items' => fn ($query) => $query->orderBy('id'),
                'payments' => fn ($query) => $query->with('statusEvents')->orderBy('id'),
                'statusEvents' => fn ($query) => $query->orderBy('id'),
            ])
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when($isBranchAdmin, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->latest('id')
            ->limit(150)
            ->get();

        $branches = Branch::query()
            ->with('organization:id,name')
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when($isBranchAdmin, fn ($query) => $query->whereIn('id', $branchIds))
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'code', 'is_active']);

        $organizations = $isSuperadmin
            ? Organization::query()->orderBy('name')->get(['id', 'name'])
            : [];

        $statuses = collect(OrderStatus::cases())->map(fn (OrderStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return Inertia::render('cms/Orders', [
            'orders' => OrderResource::collection($orders)->resolve(),
            'branches' => $branches,
            'organizations' => $organizations,
            'orderStatuses' => $statuses,
        ]);
    }
}
