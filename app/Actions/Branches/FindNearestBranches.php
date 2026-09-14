<?php

namespace App\Actions\Branches;

use App\Data\NearestBranchData;
use App\Data\NearestBranchQueryData;
use App\Models\Branch;
use App\Models\Organization;
use App\Services\DeliveryRouteCalculator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final readonly class FindNearestBranches
{
    public function __construct(private DeliveryRouteCalculator $routeCalculator) {}

    /** @return Collection<int, NearestBranchData> */
    public function handle(?Organization $organization, NearestBranchQueryData $query): Collection
    {
        if ($organization !== null && ! $organization->is_active) {
            throw (new ModelNotFoundException)->setModel(Organization::class, [$organization->id]);
        }

        $branches = Branch::query()
            ->with(['city:id,name,province', 'organization:id,slug'])
            ->when(
                $organization !== null,
                fn ($branchQuery) => $branchQuery->where('organization_id', $organization?->id),
                fn ($branchQuery) => $branchQuery->whereHas(
                    'organization',
                    fn ($organizationQuery) => $organizationQuery->where('is_active', true),
                ),
            )
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn (Branch $branch): NearestBranchData => new NearestBranchData(
                branch: $branch,
                deliveryQuote: $this->routeCalculator->quote(
                    $branch,
                    $query->latitude,
                    $query->longitude,
                ),
            ))
            ->sortBy(fn (NearestBranchData $result): int => $result->deliveryQuote->distanceMeters)
            ->values();

        return $query->limit === null ? $branches : $branches->take($query->limit)->values();
    }
}
