<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Branches\CreateBranch;
use App\Actions\Branches\DeleteBranch;
use App\Actions\Branches\UpdateBranch;
use App\Data\BranchData;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteBranchRequest;
use App\Http\Requests\Cms\StoreBranchRequest;
use App\Http\Requests\Cms\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Branch::class);

        /** @var User $user */
        $user = request()->user();
        $isSuperadmin = $user->hasRole(RoleName::Superadmin->value);

        return Inertia::render('cms/Branches', [
            'branches' => Branch::query()
                ->with(['city:id,name,province', 'organization:id,name'])
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
                ->orderBy('name')
                ->get(),
            'cities' => City::query()
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name', 'province']),
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreBranchRequest $request, CreateBranch $createBranch): RedirectResponse
    {
        $createBranch->handle(BranchData::fromRequest($request));

        return back()->with('success', 'Cabang berhasil dibuat.');
    }

    public function update(
        UpdateBranchRequest $request,
        Branch $branch,
        UpdateBranch $updateBranch,
    ): RedirectResponse {
        $updateBranch->handle($branch, BranchData::fromRequest($request));

        return back()->with('success', 'Cabang berhasil diperbarui.');
    }

    public function destroy(
        DeleteBranchRequest $request,
        Branch $branch,
        DeleteBranch $deleteBranch,
    ): RedirectResponse {
        $deleteBranch->handle($branch);

        return back()->with('success', 'Cabang berhasil dihapus.');
    }
}
