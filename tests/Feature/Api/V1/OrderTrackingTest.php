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

it('returns a customer own canonical order with its ordered timeline', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create(['name' => 'Budi']);
    $customer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
        'customer_name_snapshot' => 'Budi',
        'status' => OrderStatus::Washing,
    ]);
    $order->statusEvents()->createMany([
        [
            'actor_id' => $customer->id,
            'actor_name_snapshot' => 'Budi',
            'status' => OrderStatus::Received,
        ],
        [
            'actor_id' => $customer->id,
            'actor_name_snapshot' => 'Budi',
            'status' => OrderStatus::Weighed,
            'note' => 'Berat sudah dikonfirmasi',
        ],
        [
            'actor_id' => $customer->id,
            'actor_name_snapshot' => 'Budi',
            'status' => OrderStatus::Washing,
        ],
    ]);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.organizations.branches.orders.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]))->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.status', OrderStatus::Washing->value)
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.status_history.0.status', OrderStatus::Received->value)
        ->assertJsonPath('data.status_history.1.status', OrderStatus::Weighed->value)
        ->assertJsonPath('data.status_history.1.note', 'Berat sudah dikonfirmasi')
        ->assertJsonPath('data.status_history.2.status', OrderStatus::Washing->value)
        ->assertJsonCount(3, 'data.status_history');
});

it('rejects unauthenticated users and customers viewing another customer order', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $otherCustomer = User::factory()->create();
    $otherCustomer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);
    $route = route('api.v1.organizations.branches.orders.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);

    $this->getJson($route)->assertUnauthorized();

    Sanctum::actingAs($otherCustomer);
    $this->getJson($route)->assertForbidden();
});

it('enforces staff role, tenant, and branch assignment when viewing an order', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $order = Order::factory()->for($organization)->for($branch)->create();
    $route = route('api.v1.organizations.branches.orders.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);
    $this->getJson($route)->assertOk();

    $unassignedAdmin = User::factory()->for($organization)->create();
    $unassignedAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($unassignedAdmin);
    $this->getJson($route)->assertForbidden();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($owner);
    $this->getJson($route)->assertOk();

    $otherOwner = User::factory()->forOrganization()->create();
    $otherOwner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($otherOwner);
    $this->getJson($route)->assertForbidden();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);
    Sanctum::actingAs($superadmin);
    $this->getJson($route)->assertOk();
});

it('returns not found when any nested tenant binding does not match', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.organizations.branches.orders.show', [
        'organization' => $otherOrganization,
        'branch' => $branch,
        'order' => $order,
    ]))->assertNotFound();

    $this->getJson(route('api.v1.organizations.branches.orders.show', [
        'organization' => $organization,
        'branch' => $otherBranch,
        'order' => $order,
    ]))->assertNotFound();
});
