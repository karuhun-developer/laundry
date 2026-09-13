<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

final class DeleteOrganization
{
    public function handle(Organization $organization): void
    {
        if ($organization->branches()->exists()
            || $organization->services()->exists()
            || $organization->orders()->exists()
            || $organization->users()->exists()) {
            throw ValidationException::withMessages([
                'confirmation' => 'Organisasi masih memiliki cabang, layanan, order, atau pengguna.',
            ]);
        }

        $organization->delete();
    }
}
