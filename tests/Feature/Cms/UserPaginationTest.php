<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('paginates users and calculates total statistics across all pages', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    // Create 5 branch admins
    for ($i = 1; $i <= 5; $i++) {
        $staff = User::factory()->for($organization)->create();
        $staff->assignRole(RoleName::BranchAdmin->value);
        $staff->branches()->attach($branch);
    }

    // Create 24 regular customers
    // Total users in organization = 1 owner + 5 staff + 24 customers = 30 users
    User::factory()->for($organization)->count(24)->create()->each(function (User $user): void {
        $user->assignRole(RoleName::Customer->value);
    });

    // Page 1: 25 users
    $this->actingAs($owner)
        ->get(route('cms.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Users')
            ->has('users', 25)
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 30)
            ->where('statistics.total', 30)
            ->where('statistics.branch_admins', 5)
        );

    // Page 2: remaining 5 users
    $this->actingAs($owner)
        ->get(route('cms.users.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Users')
            ->has('users', 5)
            ->where('pagination.current_page', 2)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 30)
            ->where('statistics.total', 30)
            ->where('statistics.branch_admins', 5)
        );
});
