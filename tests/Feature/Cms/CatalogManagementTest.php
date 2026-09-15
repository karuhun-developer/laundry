<?php

use App\Enums\PriceType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows an owner to create services and branch prices in their organization', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.services.store'), [
        'name' => 'Cuci Kering Lipat',
        'code' => 'ckl',
        'description' => 'Pakaian harian.',
    ])->assertRedirect();

    $service = Service::query()->where('organization_id', $organization->id)->firstOrFail();

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $service), [
        'branch_id' => $branch->id,
        'type' => PriceType::FixedPackage->value,
        'name' => 'Paket 12 kg',
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
        'price_per_kilogram_rupiah' => 12000,
    ])->assertRedirect();

    $this->assertDatabaseHas('services', [
        'organization_id' => $organization->id,
        'code' => 'CKL',
    ]);
    $this->assertDatabaseHas('price_options', [
        'service_id' => $service->id,
        'branch_id' => $branch->id,
        'type' => PriceType::FixedPackage->value,
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
    ]);
});

it('allows an owner to create one price for all current and future branches', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $payload = [
        'type' => PriceType::PerKilogram->value,
        'name' => 'Kiloan semua cabang',
        'price_per_kilogram_rupiah' => 15000,
    ];

    $this->actingAs($owner)
        ->post(route('cms.services.price-options.store', $service), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('price_options', [
        'service_id' => $service->id,
        'branch_id' => null,
        'name' => 'Kiloan semua cabang',
    ]);

    $this->actingAs($owner)
        ->post(route('cms.services.price-options.store', $service), $payload)
        ->assertSessionHasErrors('name');

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $service), [
        ...$payload,
        'branch_id' => $branch->id,
    ])->assertRedirect();

    expect($service->priceOptions()->where('name', 'Kiloan semua cabang')->count())->toBe(2);
});

it('allows a superadmin to target an organization when creating a service', function (): void {
    $organization = Organization::factory()->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.services.store'), [
        'organization_id' => $organization->id,
        'name' => 'Dry Cleaning',
        'code' => 'DRY',
    ])->assertRedirect();

    $this->assertDatabaseHas('services', [
        'organization_id' => $organization->id,
        'code' => 'DRY',
    ]);
});

it('forbids an owner without an organization from creating services', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.services.store'), [
        'organization_id' => $organization->id,
        'name' => 'Layanan Silang',
        'code' => 'SILANG',
    ])->assertForbidden();

    $this->assertDatabaseMissing('services', ['code' => 'SILANG']);
});

it('forbids an owner without an organization from creating price options', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $service), [
        'branch_id' => $branch->id,
        'type' => PriceType::PerKilogram->value,
        'name' => 'Tarif Silang',
        'price_per_kilogram_rupiah' => 12000,
    ])->assertForbidden();

    $this->assertDatabaseMissing('price_options', ['name' => 'Tarif Silang']);
});

it('rejects cross-tenant branch prices and services', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $otherService = Service::factory()->for($otherOrganization)->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $service), [
        'branch_id' => $otherBranch->id,
        'type' => PriceType::PerKilogram->value,
        'name' => 'Tarif silang',
        'price_per_kilogram_rupiah' => 12000,
    ])->assertSessionHasErrors('branch_id');

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $otherService), [
        'branch_id' => $otherBranch->id,
        'type' => PriceType::PerKilogram->value,
        'name' => 'Tarif asing',
        'price_per_kilogram_rupiah' => 12000,
    ])->assertForbidden();

    $this->assertDatabaseMissing('price_options', ['name' => 'Tarif silang']);
    $this->assertDatabaseMissing('price_options', ['name' => 'Tarif asing']);
});

it('validates fields according to the selected pricing type', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $service), [
        'branch_id' => $branch->id,
        'type' => PriceType::FixedPackage->value,
        'name' => 'Paket tidak lengkap',
    ])->assertSessionHasErrors(['package_weight_grams', 'base_price_rupiah']);

    $this->actingAs($owner)->post(route('cms.services.price-options.store', $service), [
        'branch_id' => $branch->id,
        'type' => PriceType::PerKilogram->value,
        'name' => 'Kiloan tidak valid',
        'package_weight_grams' => 12000,
    ])->assertSessionHasErrors(['package_weight_grams', 'price_per_kilogram_rupiah']);
});

it('forbids branch admins and customers from managing the catalog', function (RoleName $role): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();
    $user->assignRole($role->value);

    $request = $this->actingAs($user);

    if ($role === RoleName::Customer) {
        $request = $request->withSession(['customer_session_version' => $user->session_version]);
    }

    $request->post(route('cms.services.store'), [
        'name' => 'Tidak boleh',
        'code' => 'DENIED',
    ])->assertForbidden();
})->with([RoleName::BranchAdmin, RoleName::Customer]);
