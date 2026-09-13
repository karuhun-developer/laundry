<?php

use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;

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
