<?php

use App\Enums\PriceType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows an owner to update their service without changing its organization', function (): void {
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.services.update', $service), [
        'name' => 'Cuci Premium',
        'code' => 'premium',
        'description' => 'Layanan pakaian premium.',
        'is_active' => false,
        'organization_id' => Organization::factory()->create()->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'organization_id' => $organization->id,
        'name' => 'Cuci Premium',
        'code' => 'PREMIUM',
        'description' => 'Layanan pakaian premium.',
        'is_active' => false,
    ]);
});

it('allows an owner to update a package with a branch in the same organization', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.price-options.update', $priceOption), [
        'branch_id' => $branch->id,
        'type' => PriceType::FixedPackage->value,
        'name' => 'Paket Hemat 12 kg',
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
        'price_per_kilogram_rupiah' => 15000,
        'is_active' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('price_options', [
        'id' => $priceOption->id,
        'branch_id' => $branch->id,
        'type' => PriceType::FixedPackage->value,
        'name' => 'Paket Hemat 12 kg',
        'package_weight_grams' => 12000,
        'base_price_rupiah' => 120000,
        'price_per_kilogram_rupiah' => 15000,
    ]);
});

it('rejects a package update to another organization branch', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.price-options.update', $priceOption), [
        'branch_id' => $otherBranch->id,
        'type' => PriceType::PerKilogram->value,
        'name' => 'Tarif silang',
        'price_per_kilogram_rupiah' => 15000,
    ])->assertSessionHasErrors('branch_id');

    expect($priceOption->fresh()?->branch_id)->toBe($branch->id);
});

it('requires exact confirmation and blocks deleting packages used by orders', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create([
        'name' => 'Kiloan reguler',
    ]);
    OrderItem::factory()->create([
        'service_id' => $service->id,
        'price_option_id' => $priceOption->id,
    ]);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->delete(route('cms.price-options.destroy', $priceOption), [
        'confirmation' => 'salah',
    ])->assertSessionHasErrors('confirmation');

    $this->actingAs($owner)->delete(route('cms.price-options.destroy', $priceOption), [
        'confirmation' => 'Kiloan reguler',
    ])->assertSessionHasErrors([
        'confirmation' => 'Paket yang sudah dipakai order tidak dapat dihapus. Nonaktifkan paket agar riwayat tetap aman.',
    ]);

    $this->assertModelExists($priceOption);
});

it('deletes an unused package and then its empty service', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create(['name' => 'Cuci Kering']);
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create([
        'name' => 'Kiloan reguler',
    ]);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->delete(route('cms.services.destroy', $service), [
        'confirmation' => 'Cuci Kering',
    ])->assertSessionHasErrors('confirmation');

    $this->actingAs($owner)->delete(route('cms.price-options.destroy', $priceOption), [
        'confirmation' => 'Kiloan reguler',
    ])->assertRedirect();
    $this->assertModelMissing($priceOption);

    $this->actingAs($owner)->delete(route('cms.services.destroy', $service), [
        'confirmation' => 'Cuci Kering',
    ])->assertRedirect();
    $this->assertModelMissing($service);
});

it('forbids an owner from updating or deleting another tenant catalog', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();
    $otherService = Service::factory()->for($otherOrganization)->create();
    $otherPriceOption = PriceOption::factory()->for($otherService)->for($otherBranch)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.services.update', $otherService), [
        'name' => 'Diambil alih',
        'code' => 'TAKEOVER',
    ])->assertForbidden();

    $this->actingAs($owner)->delete(route('cms.price-options.destroy', $otherPriceOption), [
        'confirmation' => $otherPriceOption->name,
    ])->assertForbidden();
});
