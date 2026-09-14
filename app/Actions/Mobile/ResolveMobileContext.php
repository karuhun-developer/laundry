<?php

namespace App\Actions\Mobile;

use App\Data\MobileContextData;
use App\Data\MobileContextQueryData;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ResolveMobileContext
{
    public function handle(User $user, MobileContextQueryData $query): MobileContextData
    {
        $role = $this->operationalRole($user);
        $organizations = Organization::query()
            ->where('is_active', true)
            ->withExists([
                'branches as has_active_branches' => fn (Builder $branchQuery): Builder => $branchQuery
                    ->where('is_active', true),
            ])
            ->orderByDesc('has_active_branches')
            ->orderBy('name')
            ->get();

        $organization = $role === RoleName::BranchAdmin
            ? Organization::query()->whereKey($user->organization_id)->where('is_active', true)->firstOrFail()
            : $this->customerOrganization($user, $query, $organizations);

        $branches = $role === RoleName::Customer
            ? $this->customerBranches()
            : $organization->branches()
                ->with(['city:id,name,province', 'organization:id,slug'])
                ->where('is_active', true)
                ->whereHas(
                    'staff',
                    fn (Builder $staffQuery): Builder => $staffQuery->whereKey($user->id),
                )
                ->orderBy('name')
                ->get();

        return new MobileContextData(
            role: $role,
            organization: $organization,
            organizations: $role === RoleName::Customer ? $organizations : collect([$organization]),
            branches: $branches,
        );
    }

    /** @return Collection<int, Branch> */
    private function customerBranches(): Collection
    {
        return Branch::query()
            ->with(['city:id,name,province', 'organization:id,slug'])
            ->where('is_active', true)
            ->whereHas(
                'organization',
                fn (Builder $organizationQuery): Builder => $organizationQuery->where('is_active', true),
            )
            ->orderBy('name')
            ->get();
    }

    private function operationalRole(User $user): RoleName
    {
        if ($user->hasRole(RoleName::BranchAdmin->value)) {
            return RoleName::BranchAdmin;
        }

        if ($user->hasRole(RoleName::Customer->value)) {
            return RoleName::Customer;
        }

        throw new AuthorizationException;
    }

    /** @param  Collection<int, Organization>  $organizations */
    private function customerOrganization(
        User $user,
        MobileContextQueryData $query,
        Collection $organizations,
    ): Organization {
        if ($user->organization_id !== null) {
            return Organization::query()
                ->whereKey($user->organization_id)
                ->where('is_active', true)
                ->firstOrFail();
        }

        if ($query->organizationSlug !== null) {
            return Organization::query()
                ->where('slug', $query->organizationSlug)
                ->where('is_active', true)
                ->firstOrFail();
        }

        return $organizations->firstOrFail();
    }
}
