<?php

namespace App\Actions\Users;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListOrganizationCustomers
{
    /** @return Collection<int, User> */
    public function handle(Organization $organization): Collection
    {
        return $organization->customers()
            ->customerOnly()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();
    }
}
