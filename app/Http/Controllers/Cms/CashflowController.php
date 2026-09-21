<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Cashflow\CreateCashflowEntry;
use App\Actions\Cashflow\DeleteCashflowEntry;
use App\Data\CashflowEntryData;
use App\Enums\CashflowType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCashflowEntryRequest;
use App\Models\Branch;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CashflowController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CashflowEntry::class);

        /** @var User $user */
        $user = $request->user();
        $isSuperadmin = $user->hasRole(RoleName::Superadmin->value);
        $year = (int) $request->input('year', now()->year);

        $entriesQuery = CashflowEntry::query()
            ->with(['category:id,name,type,group', 'branch:id,name'])
            ->whereYear('entry_date', $year)
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(
                $request->filled('organization_id') && $isSuperadmin,
                fn ($query) => $query->where('organization_id', $request->input('organization_id')),
            )
            ->when(
                $request->filled('branch_id'),
                fn ($query) => $query->where('branch_id', $request->input('branch_id')),
            );

        $entries = $entriesQuery->oldest('entry_date')->oldest('id')->get();

        $statement = $this->buildStatement($entries, $year);

        $branches = Branch::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(
                $request->filled('organization_id') && $isSuperadmin,
                fn ($query) => $query->where('organization_id', $request->input('organization_id')),
            )
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name']);

        $organizations = $isSuperadmin
            ? Organization::query()->orderBy('name')->get(['id', 'name'])
            : [];

        $categories = CashflowCategory::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(
                $request->filled('organization_id') && $isSuperadmin,
                fn ($query) => $query->where('organization_id', $request->input('organization_id')),
            )
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'type', 'group', 'is_system']);

        return Inertia::render('cms/Cashflow', [
            'statement' => $statement,
            'entries' => $entries,
            'branches' => $branches,
            'organizations' => $organizations,
            'categories' => $categories,
            'filters' => [
                'year' => $year,
                'branch_id' => $request->input('branch_id'),
                'organization_id' => $request->input('organization_id'),
            ],
        ]);
    }

    public function store(
        StoreCashflowEntryRequest $request,
        CreateCashflowEntry $createCashflowEntry,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $createCashflowEntry->handle(
            CashflowEntryData::fromRequest($request),
            $user,
        );

        return back()->with('success', 'Entri cashflow berhasil ditambahkan.');
    }

    public function destroy(
        CashflowEntry $cashflowEntry,
        DeleteCashflowEntry $deleteCashflowEntry,
    ): RedirectResponse {
        Gate::authorize('delete', $cashflowEntry);

        $deleteCashflowEntry->handle($cashflowEntry);

        return back()->with('success', 'Entri cashflow berhasil dihapus.');
    }

    /**
     * Build the cashflow statement array grouped by category per month.
     *
     * @param  Collection<int, CashflowEntry>  $entries
     * @return array{
     *     year: int,
     *     total_debit: int,
     *     total_credit: int,
     *     net_profit: int,
     *     months: array<int, array{month: int, debit: int, credit: int, net: int}>,
     *     categories: array<int, array{id: int, name: string, type: string, group: string, monthly: array<int, int>, total: int}>,
     * }
     */
    private function buildStatement(Collection $entries, int $year): array
    {
        /** @var array<int, array{id: int, name: string, type: string, group: string, monthly: array<int, int>, total: int}> $categoryData */
        $categoryData = [];
        $monthlyDebit = array_fill(1, 12, 0);
        $monthlyCredit = array_fill(1, 12, 0);

        foreach ($entries as $entry) {
            $month = $entry->entry_date->month;
            $catId = $entry->cashflow_category_id;

            if (! isset($categoryData[$catId])) {
                $categoryData[$catId] = [
                    'id' => $catId,
                    'name' => $entry->category->name,
                    'type' => $entry->category->type->value,
                    'group' => $entry->category->group->value,
                    'monthly' => array_fill(1, 12, 0),
                    'total' => 0,
                ];
            }

            $categoryData[$catId]['monthly'][$month] += $entry->amount_rupiah;
            $categoryData[$catId]['total'] += $entry->amount_rupiah;

            if ($entry->type === CashflowType::Debit) {
                $monthlyDebit[$month] += $entry->amount_rupiah;
            } else {
                $monthlyCredit[$month] += $entry->amount_rupiah;
            }
        }

        $totalDebit = array_sum($monthlyDebit);
        $totalCredit = array_sum($monthlyCredit);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = [
                'month' => $m,
                'debit' => $monthlyDebit[$m],
                'credit' => $monthlyCredit[$m],
                'net' => $monthlyDebit[$m] - $monthlyCredit[$m],
            ];
        }

        return [
            'year' => $year,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'net_profit' => $totalDebit - $totalCredit,
            'months' => $months,
            'categories' => array_values($categoryData),
        ];
    }
}
