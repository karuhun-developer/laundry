<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('redirects guests from catalog management to login', function (): void {
    $this->get(route('cms.services.index'))->assertRedirect(route('login'));
});

it('shows an owner only their services packages and branches', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Jakarta']);
    $otherOrganization = Organization::factory()->create(['name' => 'Laundry Bandung']);
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Kemang']);
    Branch::factory()->for($otherOrganization)->create(['name' => 'Cabang Dago']);
    $service = Service::factory()->for($organization)->create(['name' => 'Cuci Kering']);
    Service::factory()->for($otherOrganization)->create(['name' => 'Dry Clean']);
    PriceOption::factory()->for($service)->for($branch)->create(['name' => 'Kiloan reguler']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->get(route('cms.services.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/ServiceCatalog')
            ->has('services', 1)
            ->where('services.0.name', 'Cuci Kering')
            ->where('services.0.price_options.0.name', 'Kiloan reguler')
            ->has('branches', 1)
            ->where('branches.0.name', 'Cabang Kemang')
            ->has('organizations', 0));
});

it('shows every organization catalog to a superadmin', function (): void {
    $firstOrganization = Organization::factory()->create(['name' => 'Laundry Jakarta']);
    $secondOrganization = Organization::factory()->create(['name' => 'Laundry Bandung']);
    Branch::factory()->for($firstOrganization)->create();
    Branch::factory()->for($secondOrganization)->create();
    Service::factory()->for($firstOrganization)->create();
    Service::factory()->for($secondOrganization)->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->get(route('cms.services.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/ServiceCatalog')
            ->has('services', 2)
            ->has('branches', 2)
            ->has('organizations', 2));
});

it('forbids branch admins from opening catalog management', function (): void {
    $organization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);

    $this->actingAs($branchAdmin)->get(route('cms.services.index'))->assertForbidden();
});
