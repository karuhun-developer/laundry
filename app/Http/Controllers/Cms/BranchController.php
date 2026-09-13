<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Branches\CreateBranch;
use App\Data\BranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreBranchRequest;
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

        return Inertia::render('cms/Branches', [
            'branches' => Branch::query()
                ->with(['city:id,name,province', 'organization:id,name'])
                ->when($user->organization_id !== null, fn ($query) => $query->where('organization_id', $user->organization_id))
                ->orderBy('name')
                ->get(),
            'cities' => City::query()
                ->when($user->organization_id !== null, fn ($query) => $query->where('organization_id', $user->organization_id))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name', 'province']),
            'organizations' => $user->organization_id === null
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreBranchRequest $request, CreateBranch $createBranch): RedirectResponse
    {
        $createBranch->handle(BranchData::fromRequest($request));

        return back()->with('success', 'Cabang berhasil dibuat.');
    }
}
