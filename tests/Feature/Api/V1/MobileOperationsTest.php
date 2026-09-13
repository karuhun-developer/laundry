<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('returns customer context with active organizations and branches', function (): void {
    $organization = Organization::factory()->create(['slug' => 'jakarta-laundry']);
    $branch = Branch::factory()->for($organization)->create(['name' => 'Kemang']);
    $otherOrganization = Organization::factory()->create(['slug' => 'bandung-laundry']);
    Branch::factory()->for($otherOrganization)->create(['name' => 'Dago']);
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.context', ['organization' => $organization->slug]))
        ->assertOk()
        ->assertJsonPath('data.role', RoleName::Customer->value)
        ->assertJsonPath('data.organization.slug', $organization->slug)
        ->assertJsonPath('data.branches.0.id', $branch->id)
        ->assertJsonCount(2, 'data.organizations');
});

it('defaults neutral customers to an organization with an active branch', function (): void {
    Organization::factory()->create([
        'name' => 'Alpha Laundry',
        'slug' => 'alpha-laundry',
    ]);
    $operationalOrganization = Organization::factory()->create([
        'name' => 'Zulu Laundry',
        'slug' => 'zulu-laundry',
    ]);
    $branch = Branch::factory()->for($operationalOrganization)->create();
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.context'))
        ->assertOk()
        ->assertJsonPath('data.organization.id', $operationalOrganization->id)
        ->assertJsonPath('data.branches.0.id', $branch->id);
});

it('accepts browser session authentication for mobile api requests', function (): void {
    $organization = Organization::factory()->create(['slug' => 'session-laundry']);
    Branch::factory()->for($organization)->create();
    $customer = User::factory()->create([
        'organization_id' => null,
        'email' => 'session-customer@example.test',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->withHeader('Origin', config('app.url'))
        ->getJson(route('api.v1.mobile.context', ['organization' => $organization->slug]))
        ->assertOk()
        ->assertJsonPath('data.role', RoleName::Customer->value)
        ->assertJsonPath('data.organization.id', $organization->id);
});

it('locks branch admin context to their tenant and assigned branches', function (): void {
    $organization = Organization::factory()->create();
    $assignedBranch = Branch::factory()->for($organization)->create();
    Branch::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($assignedBranch);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.mobile.context', ['organization' => $otherOrganization->slug]))
        ->assertOk()
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.branches.0.id', $assignedBranch->id);
});

it('filters customer orders to the authenticated customer', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    $ownOrder = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
        'status' => OrderStatus::Washing,
    ]);
    Order::factory()->for($organization)->for($branch)->create();
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.organizations.orders.index', $organization))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownOrder->id);
});

it('filters branch admin orders to assigned branches', function (): void {
    $organization = Organization::factory()->create();
    $assignedBranch = Branch::factory()->for($organization)->create();
    $unassignedBranch = Branch::factory()->for($organization)->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($assignedBranch);
    $visibleOrder = Order::factory()->for($organization)->for($assignedBranch)->create();
    Order::factory()->for($organization)->for($unassignedBranch)->create();
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.organizations.orders.index', $organization))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visibleOrder->id);
});

it('denies cross-tenant and unsupported role order lists', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.organizations.orders.index', $otherOrganization))
        ->assertForbidden();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($owner);

    $this->getJson(route('api.v1.organizations.orders.index', $organization))
        ->assertForbidden();
});

it('returns tenant customers and neutral customers with tenant orders to branch staff', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    $tenantCustomer = User::factory()->for($organization)->create(['name' => 'Tenant Customer']);
    $tenantCustomer->assignRole(RoleName::Customer->value);
    $neutralCustomer = User::factory()->create([
        'organization_id' => null,
        'name' => 'Neutral Customer',
    ]);
    $neutralCustomer->assignRole(RoleName::Customer->value);
    Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $neutralCustomer->id,
        'created_by_id' => $neutralCustomer->id,
    ]);
    $unrelatedNeutralCustomer = User::factory()->create([
        'organization_id' => null,
        'name' => 'Unrelated Neutral Customer',
    ]);
    $unrelatedNeutralCustomer->assignRole(RoleName::Customer->value);
    $otherCustomer = User::factory()->for($otherOrganization)->create();
    $otherCustomer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.organizations.customers.index', $organization))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $tenantCustomer->id, 'name' => 'Tenant Customer'])
        ->assertJsonFragment(['id' => $neutralCustomer->id, 'name' => 'Neutral Customer'])
        ->assertJsonMissing(['id' => $unrelatedNeutralCustomer->id])
        ->assertJsonMissing(['id' => $otherCustomer->id]);
});

it('denies customer access to the staff customer directory', function (): void {
    $organization = Organization::factory()->create();
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.organizations.customers.index', $organization))
        ->assertForbidden();
});
