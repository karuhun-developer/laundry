<?php

namespace App\Actions\Organizations;

use App\Data\OrganizationData;
use App\Models\Organization;

final class UpdateOrganization
{
    public function handle(Organization $organization, OrganizationData $data): Organization
    {
        $organization->update([
            'name' => $data->name,
            'is_active' => $data->isActive,
        ]);

        return $organization->refresh();
    }
}
