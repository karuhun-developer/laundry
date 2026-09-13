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

function createOrderTransitionContext(OrderStatus $status): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $branchAdmin = User::factory()->for($organization)->create(['name' => 'Admin Cabang']);
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'status' => $status,
    ]);
    $order->statusEvents()->create([
        'actor_id' => $customer->id,
        'actor_name_snapshot' => $customer->name,
        'status' => $status,
    ]);

    return [$organization, $branch, $order, $branchAdmin, $customer];
}

function orderStatusTransitionRoute(
    Organization $organization,
    Branch $branch,
    Order $order,
): string {
    return route('api.v1.organizations.branches.orders.status-events.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);
}

it('accepts every normal forward transition and returns the canonical order', function (
    OrderStatus $currentStatus,
    OrderStatus $requestedStatus,
): void {
    [$organization, $branch, $order, $branchAdmin] = createOrderTransitionContext($currentStatus);
    Sanctum::actingAs($branchAdmin);

    $this->postJson(orderStatusTransitionRoute($organization, $branch, $order), [
        'status' => $requestedStatus->value,
        'note' => '  Diproses oleh petugas  ',
    ])->assertOk()
        ->assertJsonPath('data.status', $requestedStatus->value)
        ->assertJsonPath('data.status_history.1.status', $requestedStatus->value)
        ->assertJsonPath('data.status_history.1.actor.name', 'Admin Cabang')
        ->assertJsonPath('data.status_history.1.note', 'Diproses oleh petugas')
        ->assertJsonCount(2, 'data.status_history');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => $requestedStatus->value,
    ]);
    $this->assertDatabaseHas('order_status_events', [
        'order_id' => $order->id,
        'actor_id' => $branchAdmin->id,
        'status' => $requestedStatus->value,
        'note' => 'Diproses oleh petugas',
    ]);
    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'orders',
        'description' => 'order status transitioned',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'causer_id' => $branchAdmin->id,
    ]);
})->with([
    'received to weighed' => [OrderStatus::Received, OrderStatus::Weighed],
    'weighed to washing' => [OrderStatus::Weighed, OrderStatus::Washing],
    'washing to drying' => [OrderStatus::Washing, OrderStatus::Drying],
    'drying to ironing' => [OrderStatus::Drying, OrderStatus::Ironing],
    'ironing to ready for pickup' => [OrderStatus::Ironing, OrderStatus::ReadyForPickup],
    'ready for pickup to completed' => [OrderStatus::ReadyForPickup, OrderStatus::Completed],
]);

it('allows cancellation from every non-terminal status', function (OrderStatus $currentStatus): void {
    [$organization, $branch, $order, $branchAdmin] = createOrderTransitionContext($currentStatus);
    Sanctum::actingAs($branchAdmin);

    $this->postJson(orderStatusTransitionRoute($organization, $branch, $order), [
        'status' => OrderStatus::Cancelled->value,
    ])->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

    $this->assertDatabaseHas('order_status_events', [
        'order_id' => $order->id,
        'status' => OrderStatus::Cancelled->value,
        'note' => null,
    ]);
})->with([
    OrderStatus::Received,
    OrderStatus::Weighed,
    OrderStatus::Washing,
    OrderStatus::Drying,
    OrderStatus::Ironing,
    OrderStatus::ReadyForPickup,
]);

it('rejects skipped, backward, duplicate, and terminal transitions without side effects', function (
    OrderStatus $currentStatus,
    OrderStatus $requestedStatus,
): void {
    [$organization, $branch, $order, $branchAdmin] = createOrderTransitionContext($currentStatus);
    Sanctum::actingAs($branchAdmin);

    $this->postJson(orderStatusTransitionRoute($organization, $branch, $order), [
        'status' => $requestedStatus->value,
    ])->assertConflict()
        ->assertJsonPath('code', 'INVALID_ORDER_STATUS_TRANSITION')
        ->assertJsonValidationErrors('status');

    expect($order->fresh()?->status)->toBe($currentStatus)
        ->and($order->statusEvents()->count())->toBe(1);
    $this->assertDatabaseMissing('activity_log', [
        'description' => 'order status transitioned',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
    ]);
})->with([
    'skipped' => [OrderStatus::Received, OrderStatus::Washing],
    'backward' => [OrderStatus::Washing, OrderStatus::Weighed],
    'duplicate' => [OrderStatus::Drying, OrderStatus::Drying],
    'completed terminal' => [OrderStatus::Completed, OrderStatus::Cancelled],
    'cancelled terminal' => [OrderStatus::Cancelled, OrderStatus::Received],
]);

it('rejects customers, unassigned branch admins, and cross-tenant owners', function (): void {
    [$organization, $branch, $order, $branchAdmin, $customer] = createOrderTransitionContext(
        OrderStatus::Received,
    );
    $route = orderStatusTransitionRoute($organization, $branch, $order);
    $payload = ['status' => OrderStatus::Weighed->value];

    $this->postJson($route, $payload)->assertUnauthorized();

    Sanctum::actingAs($customer);
    $this->postJson($route, $payload)->assertForbidden();

    $unassignedAdmin = User::factory()->for($organization)->create();
    $unassignedAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($unassignedAdmin);
    $this->postJson($route, $payload)->assertForbidden();

    $otherOwner = User::factory()->forOrganization()->create();
    $otherOwner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($otherOwner);
    $this->postJson($route, $payload)->assertForbidden();

    expect($order->fresh()?->status)->toBe(OrderStatus::Received)
        ->and($order->statusEvents()->count())->toBe(1);
});

it('allows an owner in their tenant and a superadmin across tenants', function (): void {
    [$organization, $branch, $order] = createOrderTransitionContext(OrderStatus::Received);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($owner);

    $this->postJson(orderStatusTransitionRoute($organization, $branch, $order), [
        'status' => OrderStatus::Weighed->value,
    ])->assertOk();

    [$otherOrganization, $otherBranch, $otherOrder] = createOrderTransitionContext(
        OrderStatus::Received,
    );
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);
    Sanctum::actingAs($superadmin);

    $this->postJson(orderStatusTransitionRoute(
        $otherOrganization,
        $otherBranch,
        $otherOrder,
    ), [
        'status' => OrderStatus::Weighed->value,
    ])->assertOk();
});

it('validates status, note length, and unknown server-owned fields', function (): void {
    [$organization, $branch, $order, $branchAdmin] = createOrderTransitionContext(
        OrderStatus::Received,
    );
    Sanctum::actingAs($branchAdmin);
    $route = orderStatusTransitionRoute($organization, $branch, $order);

    $this->postJson($route, [])->assertUnprocessable()
        ->assertJsonValidationErrors('status')
        ->assertJsonPath('errors.status.0', 'Status tujuan wajib diisi.');

    $this->postJson($route, [
        'status' => 'delivered',
        'note' => str_repeat('a', 501),
        'total_rupiah' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'note', 'total_rupiah']);

    expect($order->fresh()?->status)->toBe(OrderStatus::Received)
        ->and($order->statusEvents()->count())->toBe(1);
});

it('returns not found for a cross-branch nested order', function (): void {
    [$organization, $branch, $order, $branchAdmin] = createOrderTransitionContext(
        OrderStatus::Received,
    );
    $otherBranch = Branch::factory()->for($organization)->create();
    $branchAdmin->branches()->attach($otherBranch);
    Sanctum::actingAs($branchAdmin);

    $this->postJson(orderStatusTransitionRoute($organization, $otherBranch, $order), [
        'status' => OrderStatus::Weighed->value,
    ])->assertNotFound();

    expect($order->fresh()?->status)->toBe(OrderStatus::Received)
        ->and($order->statusEvents()->count())->toBe(1);
});
