<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Services\CreateService;
use App\Actions\Services\DeleteService;
use App\Actions\Services\UpdateService;
use App\Data\ServiceData;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteServiceRequest;
use App\Http\Requests\Cms\StoreServiceRequest;
use App\Http\Requests\Cms\UpdateServiceRequest;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Service::class);

        /** @var User $user */
        $user = request()->user();
        $isSuperadmin = $user->hasRole(RoleName::Superadmin->value);

        return Inertia::render('cms/ServiceCatalog', [
            'services' => Service::query()
                ->with([
                    'organization:id,name',
                    'priceOptions' => fn ($query) => $query
                        ->with('branch:id,organization_id,name,code')
                        ->orderBy('name'),
                ])
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
                ->orderBy('name')
                ->get(),
            'branches' => Branch::query()
                ->with('organization:id,name')
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name', 'code', 'is_active']),
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreServiceRequest $request, CreateService $createService): RedirectResponse
    {
        $createService->handle(ServiceData::fromRequest($request));

        return back()->with('success', 'Layanan berhasil dibuat.');
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service,
        UpdateService $updateService,
    ): RedirectResponse {
        $updateService->handle($service, ServiceData::fromRequest($request));

        return back()->with('success', 'Layanan berhasil diperbarui.');
    }

    public function destroy(
        DeleteServiceRequest $request,
        Service $service,
        DeleteService $deleteService,
    ): RedirectResponse {
        $deleteService->handle($service);

        return back()->with('success', 'Layanan berhasil dihapus.');
    }
}
