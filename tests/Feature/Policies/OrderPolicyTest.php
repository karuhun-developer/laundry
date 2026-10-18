<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('enforces the complete role and tenant matrix for order creation', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);

    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $otherTenantCustomer = User::factory()->for($otherOrganization)->create();
    $otherTenantCustomer->assignRole(RoleName::Customer->value);

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $orphanOwner = User::factory()->create();
    $orphanOwner->assignRole(RoleName::Owner->value);

    $unprivileged = User::factory()->create();

    expect(Gate::forUser($owner)->allows('create', [Order::class, $organization, $branch]))
        ->toBeTrue()
        ->and(Gate::forUser($owner)->allows('create', [Order::class, $otherOrganization, $otherBranch]))
        ->toBeFalse()
        ->and(Gate::forUser($branchAdmin)->allows('create', [Order::class, $organization, $branch]))
        ->toBeTrue()
        ->and(Gate::forUser($branchAdmin)->allows('create', [Order::class, $otherOrganization, $otherBranch]))
        ->toBeFalse()
        ->and(Gate::forUser($customer)->allows('create', [Order::class, $organization, $branch]))
        ->toBeTrue()
        ->and(Gate::forUser($otherTenantCustomer)->allows('create', [Order::class, $organization, $branch]))
        ->toBeFalse()
        ->and(Gate::forUser($superadmin)->allows('create', [Order::class, $organization, $branch]))
        ->toBeTrue()
        ->and(Gate::forUser($orphanOwner)->allows('create', [Order::class, $organization, $branch]))
        ->toBeFalse()
        ->and(Gate::forUser($unprivileged)->allows('create', [Order::class, $organization, $branch]))
        ->toBeFalse();
});

it('enforces viewing and status updates by customer ownership, tenant, and branch assignment', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $order = Order::factory()->for($organization)->for($branch)->create();

    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $order->update(['customer_id' => $customer->id]);

    $otherCustomer = User::factory()->create();
    $otherCustomer->assignRole(RoleName::Customer->value);

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);

    $unassignedAdmin = User::factory()->for($organization)->create();
    $unassignedAdmin->assignRole(RoleName::BranchAdmin->value);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $otherOwner = User::factory()->for($otherOrganization)->create();
    $otherOwner->assignRole(RoleName::Owner->value);

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    expect(Gate::forUser($customer)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('updateStatus', $order))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('confirmPricing', $order))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('delete', $order))->toBeFalse()
        ->and(Gate::forUser($otherCustomer)->allows('view', $order))->toBeFalse()
        ->and(Gate::forUser($otherCustomer)->allows('delete', $order))->toBeFalse()
        ->and(Gate::forUser($branchAdmin)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($branchAdmin)->allows('updateStatus', $order))->toBeTrue()
        ->and(Gate::forUser($branchAdmin)->allows('confirmPricing', $order))->toBeTrue()
        ->and(Gate::forUser($branchAdmin)->allows('delete', $order))->toBeTrue()
        ->and(Gate::forUser($unassignedAdmin)->allows('view', $order))->toBeFalse()
        ->and(Gate::forUser($unassignedAdmin)->allows('updateStatus', $order))->toBeFalse()
        ->and(Gate::forUser($unassignedAdmin)->allows('confirmPricing', $order))->toBeFalse()
        ->and(Gate::forUser($unassignedAdmin)->allows('delete', $order))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('updateStatus', $order))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('confirmPricing', $order))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $order))->toBeTrue()
        ->and(Gate::forUser($otherOwner)->allows('view', $order))->toBeFalse()
        ->and(Gate::forUser($otherOwner)->allows('updateStatus', $order))->toBeFalse()
        ->and(Gate::forUser($otherOwner)->allows('confirmPricing', $order))->toBeFalse()
        ->and(Gate::forUser($otherOwner)->allows('delete', $order))->toBeFalse()
        ->and(Gate::forUser($superadmin)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('updateStatus', $order))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('confirmPricing', $order))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('delete', $order))->toBeTrue();
});
