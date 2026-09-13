<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Organizations\CreateOrganization;
use App\Data\OrganizationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreOrganizationRequest;
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
}
