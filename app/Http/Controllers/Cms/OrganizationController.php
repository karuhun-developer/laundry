<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\DeleteOrganization;
use App\Actions\Organizations\UpdateOrganization;
use App\Data\OrganizationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteOrganizationRequest;
use App\Http\Requests\Cms\StoreOrganizationRequest;
use App\Http\Requests\Cms\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Organization::class);

        return Inertia::render('cms/Organizations', [
            'organizations' => Organization::query()
                ->withCount(['branches', 'users'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(
        StoreOrganizationRequest $request,
        CreateOrganization $createOrganization,
    ): RedirectResponse {
        $createOrganization->handle(OrganizationData::fromRequest($request));

        return back()->with('success', 'Organisasi berhasil dibuat.');
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        UpdateOrganization $updateOrganization,
    ): RedirectResponse {
        $updateOrganization->handle($organization, OrganizationData::fromRequest($request));

        return back()->with('success', 'Organisasi berhasil diperbarui.');
    }

    public function destroy(
        DeleteOrganizationRequest $request,
        Organization $organization,
        DeleteOrganization $deleteOrganization,
    ): RedirectResponse {
        $deleteOrganization->handle($organization);

        return back()->with('success', 'Organisasi berhasil dihapus.');
    }
}
