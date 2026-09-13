<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Users\CreateUserWithAccess;
use App\Data\UserAccessData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreUserRequest;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = request()->user();

        return Inertia::render('cms/Users', [
            'users' => User::query()
                ->with(['roles:id,name', 'branches:id,name'])
                ->when($actor->organization_id !== null, fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'superadmin'))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name', 'email']),
            'branches' => Branch::query()
                ->when($actor->organization_id !== null, fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name']),
            'organizations' => $actor->organization_id === null
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(
        StoreUserRequest $request,
        CreateUserWithAccess $createUserWithAccess,
    ): RedirectResponse {
        $createUserWithAccess->handle(UserAccessData::fromRequest($request));

        return back()->with('success', 'Pengguna dan akses berhasil dibuat.');
    }
}
