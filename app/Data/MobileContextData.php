<?php

namespace App\Data;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Support\Collection;

final readonly class MobileContextData
{
    /**
     * @param  Collection<int, Organization>  $organizations
     * @param  Collection<int, Branch>  $branches
     */
    public function __construct(
        public RoleName $role,
        public Organization $organization,
        public Collection $organizations,
        public Collection $branches,
    ) {}
}
