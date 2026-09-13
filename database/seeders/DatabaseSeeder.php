<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $organization = Organization::query()->create([
            'name' => 'LaundryHub Demo',
            'slug' => 'laundryhub-demo',
            'is_active' => true,
        ]);

        $city = City::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'city_id' => $city->id,
            'name' => 'Cabang Kemang',
            'code' => 'KMG01',
            'phone' => '081234567890',
            'address' => 'Jl. Kemang Raya No. 10, Jakarta Selatan',
            'latitude' => -6.2607,
            'longitude' => 106.8136,
            'free_delivery_radius_meters' => 10000,
            'delivery_fee_per_km_rupiah' => 3000,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@laundryhub.test',
        ])->assignRole(RoleName::Superadmin->value);

        User::factory()->for($organization)->create([
            'name' => 'Demo Owner',
            'email' => 'owner@laundryhub.test',
        ])->assignRole(RoleName::Owner->value);

        $branchAdmin = User::factory()->for($organization)->create([
            'name' => 'Admin Kemang',
            'email' => 'branch@laundryhub.test',
        ]);
        $branchAdmin->assignRole(RoleName::BranchAdmin->value);
        $branchAdmin->branches()->attach($branch);

        User::factory()->create([
            'name' => 'Demo Customer',
            'email' => 'customer@laundryhub.test',
        ])->assignRole(RoleName::Customer->value);
    }
}
