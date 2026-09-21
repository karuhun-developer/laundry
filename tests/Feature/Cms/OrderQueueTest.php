<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('redirects guests from cms order queue to login', function (): void {
    $this->get(route('cms.orders.index'))->assertRedirect(route('login'));
});

it('forbids customers from opening cms order queue', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->actingAs($customer)
        ->withSession(['customer_session_version' => $customer->session_version])
        ->get(route('cms.orders.index'))
        ->assertForbidden();
});

it('shows a branch admin only orders from their assigned branches', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Jakarta']);
    $assignedBranch = Branch::factory()->for($organization)->create(['name' => 'Cabang Kemang']);
    $unassignedBranch = Branch::factory()->for($organization)->create(['name' => 'Cabang Tebet']);

    Order::factory()->for($organization)->for($assignedBranch)->create([
        'order_number' => 'ORD-KMG-001',
    ]);
    Order::factory()->for($organization)->for($unassignedBranch)->create([
        'order_number' => 'ORD-TBT-001',
    ]);

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($assignedBranch->id);

    $this->actingAs($branchAdmin)->get(route('cms.orders.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Orders')
            ->has('orders', 1)
            ->where('orders.0.order_number', 'ORD-KMG-001')
            ->has('branches', 1)
            ->where('branches.0.name', 'Cabang Kemang')
            ->has('organizations', 0));
});

it('shows an owner only their organization orders and branches', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Jakarta']);
    $otherOrganization = Organization::factory()->create(['name' => 'Laundry Bandung']);

    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Kemang']);
    $otherBranch = Branch::factory()->for($otherOrganization)->create(['name' => 'Cabang Dago']);

    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'ORD-JKT-001',
    ]);
    Order::factory()->for($otherOrganization)->for($otherBranch)->create([
        'order_number' => 'ORD-BDG-001',
    ]);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->get(route('cms.orders.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Orders')
            ->has('orders', 1)
            ->where('orders.0.order_number', 'ORD-JKT-001')
            ->has('branches', 1)
            ->where('branches.0.name', 'Cabang Kemang')
            ->has('organizations', 0));
});

it('shows every organization order and branches to a superadmin', function (): void {
    $firstOrganization = Organization::factory()->create(['name' => 'Laundry Jakarta']);
    $secondOrganization = Organization::factory()->create(['name' => 'Laundry Bandung']);

    $firstBranch = Branch::factory()->for($firstOrganization)->create();
    $secondBranch = Branch::factory()->for($secondOrganization)->create();

    Order::factory()->for($firstOrganization)->for($firstBranch)->create();
    Order::factory()->for($secondOrganization)->for($secondBranch)->create();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->get(route('cms.orders.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Orders')
            ->has('orders', 2)
            ->has('branches', 2)
            ->has('organizations', 2));
});
