<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('returns nearest active branches with delivery quotes in distance order', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->create();

    $farBranch = Branch::factory()->for($organization)->for($city)->create([
        'name' => 'Cabang Jauh',
        'latitude' => -6.3500,
        'longitude' => 106.8136,
        'free_delivery_radius_meters' => 5000,
        'delivery_fee_per_km_rupiah' => 2500,
    ]);
    $nearBranch = Branch::factory()->for($organization)->for($city)->create([
        'name' => 'Cabang Dekat',
        'latitude' => -6.2607,
        'longitude' => 106.8136,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3000,
    ]);

    Branch::factory()->for($organization)->for($city)->create([
        'is_active' => false,
        'latitude' => -6.2507,
        'longitude' => 106.8136,
    ]);
    Branch::factory()->for($organization)->for($city)->create([
        'latitude' => null,
        'longitude' => null,
    ]);
    Branch::factory()->create([
        'latitude' => -6.2507,
        'longitude' => 106.8136,
    ]);

    $response = $this->getJson(route('api.v1.organizations.branches.nearest', [
        'organization' => $organization,
        'latitude' => -6.2550,
        'longitude' => 106.8136,
    ]));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $nearBranch->id)
        ->assertJsonPath('data.0.delivery.within_free_radius', true)
        ->assertJsonPath('data.0.delivery.fee_rupiah', 0)
        ->assertJsonPath('data.1.id', $farBranch->id)
        ->assertJsonPath('data.1.delivery.fee_rupiah', 15000);
});

it('limits the number of nearest branches returned', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->create();

    Branch::factory()->count(3)->for($organization)->for($city)->create();

    $this->getJson(route('api.v1.organizations.branches.nearest', [
        'organization' => $organization,
        'latitude' => -6.2607,
        'longitude' => 106.8136,
        'limit' => 2,
    ]))->assertOk()->assertJsonCount(2, 'data');
});

it('rejects invalid customer coordinates', function (): void {
    $organization = Organization::factory()->create();

    $this->getJson(route('api.v1.organizations.branches.nearest', [
        'organization' => $organization,
        'latitude' => -91,
        'longitude' => 181,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('does not expose routing for an inactive organization', function (): void {
    $organization = Organization::factory()->create(['is_active' => false]);

    $this->getJson(route('api.v1.organizations.branches.nearest', [
        'organization' => $organization,
        'latitude' => -6.2607,
        'longitude' => 106.8136,
    ]))->assertNotFound();
});

it('returns every active organization branch to customers in nearest-first order', function (): void {
    $jakarta = Organization::factory()->create(['slug' => 'jakarta-laundry']);
    $bandung = Organization::factory()->create(['slug' => 'bandung-laundry']);
    $inactiveOrganization = Organization::factory()->create(['is_active' => false]);
    $city = City::factory()->create();

    $farBranch = Branch::factory()->for($jakarta)->for($city)->create([
        'name' => 'Cabang Jauh',
        'latitude' => -6.3500,
        'longitude' => 106.8136,
    ]);
    $nearBranch = Branch::factory()->for($bandung)->for($city)->create([
        'name' => 'Cabang Dekat',
        'latitude' => -6.2607,
        'longitude' => 106.8136,
    ]);
    foreach ([-6.2700, -6.2800, -6.2900, -6.3000] as $latitude) {
        Branch::factory()->for($jakarta)->for($city)->create([
            'latitude' => $latitude,
            'longitude' => 106.8136,
        ]);
    }
    Branch::factory()->for($inactiveOrganization)->for($city)->create([
        'latitude' => -6.2550,
        'longitude' => 106.8136,
    ]);
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.branches.nearest', [
        'latitude' => -6.2550,
        'longitude' => 106.8136,
    ]))
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.0.id', $nearBranch->id)
        ->assertJsonPath('data.0.organization.slug', 'bandung-laundry')
        ->assertJsonPath('data.5.id', $farBranch->id)
        ->assertJsonPath('data.5.organization.slug', 'jakarta-laundry');
});

it('requires customer authentication for the global nearest branch list', function (): void {
    $this->getJson(route('api.v1.mobile.branches.nearest', [
        'latitude' => -6.2550,
        'longitude' => 106.8136,
    ]))->assertUnauthorized();
});
