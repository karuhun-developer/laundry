<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Users\CreateUserWithAccess;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUserWithAccess;
use App\Data\UpdateUserAccessData;
use App\Data\UserAccessData;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteUserRequest;
use App\Http\Requests\Cms\StoreUserRequest;
use App\Http\Requests\Cms\UpdateUserRequest;
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
        $isSuperadmin = $actor->hasRole(RoleName::Superadmin->value);

        return Inertia::render('cms/Users', [
            'users' => User::query()
                ->with(['organization:id,name', 'roles:id,name', 'branches:id,name'])
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'superadmin'))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name', 'email']),
            'branches' => Branch::query()
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name']),
            'organizations' => $isSuperadmin
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

    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateUserWithAccess $updateUserWithAccess,
    ): RedirectResponse {
        $updateUserWithAccess->handle($user, UpdateUserAccessData::fromRequest($request));

        return back()->with('success', 'Pengguna dan akses berhasil diperbarui.');
    }

    public function destroy(
        DeleteUserRequest $request,
        User $user,
        DeleteUser $deleteUser,
    ): RedirectResponse {
        $deleteUser->handle($user, $request->user());

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
