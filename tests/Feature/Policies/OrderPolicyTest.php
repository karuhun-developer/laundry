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
        ->and(Gate::forUser($unprivileged)->allows('create', [Order::class, $organization, $branch]))
        ->toBeFalse();
});
