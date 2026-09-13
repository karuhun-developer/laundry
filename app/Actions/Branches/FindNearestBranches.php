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
    public function handle(Organization $organization, NearestBranchQueryData $query): Collection
    {
        if (! $organization->is_active) {
            throw (new ModelNotFoundException)->setModel(Organization::class, [$organization->id]);
        }

        return Branch::query()
            ->with('city:id,name,province')
            ->where('organization_id', $organization->id)
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
            ->take($query->limit)
            ->values();
    }
}
