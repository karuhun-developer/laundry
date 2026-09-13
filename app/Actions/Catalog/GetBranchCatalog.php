<?php

namespace App\Actions\Catalog;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final class GetBranchCatalog
{
    /** @return Collection<int, Service> */
    public function handle(Organization $organization, Branch $branch): Collection
    {
        if (! $organization->is_active
            || ! $branch->is_active
            || $branch->organization_id !== $organization->id) {
            throw (new ModelNotFoundException)->setModel(Branch::class, [$branch->id]);
        }

        return Service::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereHas('priceOptions', fn (Builder $query): Builder => $query
                ->where('branch_id', $branch->id)
                ->where('is_active', true))
            ->with(['priceOptions' => function (Relation $relation) use ($branch): void {
                $relation->getQuery()
                    ->where('branch_id', $branch->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->orderBy('id');
            }])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
