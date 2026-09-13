<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows a superadmin to create an organization', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'slug' => 'karuhun-laundry',
        'is_active' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Karuhun Laundry',
        'slug' => 'karuhun-laundry',
    ]);
});

it('allows an owner to create cities and branches only in their organization', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherCity = City::factory()->for($otherOrganization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.cities.store'), [
        'name' => 'Bandung',
        'province' => 'Jawa Barat',
    ])->assertRedirect();

    $city = City::query()->where('organization_id', $organization->id)->firstOrFail();

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $city->id,
        'name' => 'Cabang Dago',
        'code' => 'BDG01',
        'phone' => '081234567890',
        'address' => 'Jl. Dago No. 1',
        'latitude' => -6.8781,
        'longitude' => 107.5953,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3500,
    ])->assertRedirect();

    $this->assertDatabaseHas('branches', [
        'organization_id' => $organization->id,
        'city_id' => $city->id,
        'code' => 'BDG01',
        'latitude' => -6.8781,
        'longitude' => 107.5953,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3500,
    ]);

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $otherCity->id,
        'name' => 'Cabang Silang',
        'code' => 'BAD01',
        'address' => 'Alamat lain',
    ])->assertSessionHasErrors('city_id');

    $this->assertDatabaseMissing('branches', ['code' => 'BAD01']);
});

it('rejects branch assignments from another organization', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Admin Cabang',
        'email' => 'admin@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::BranchAdmin->value,
        'branch_ids' => [$branch->id, $otherBranch->id],
    ])->assertSessionHasErrors('branch_ids.1');

    $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
});

it('forbids customers from tenant management', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->actingAs($customer)->post(route('cms.cities.store'), [
        'name' => 'Jakarta',
        'province' => 'DKI Jakarta',
    ])->assertForbidden();
});
