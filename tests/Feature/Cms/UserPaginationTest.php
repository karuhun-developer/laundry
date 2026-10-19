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

it('searches users by name email and whatsapp within the current organization', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $byName = User::factory()->for($organization)->create([
        'name' => 'Pelanggan Melati',
        'email' => 'nama@example.test',
        'whatsapp_phone' => '628111111111',
    ]);
    $byEmail = User::factory()->for($organization)->create([
        'name' => 'Pelanggan Mawar',
        'email' => 'email-khusus@example.test',
        'whatsapp_phone' => '628222222222',
    ]);
    $byWhatsapp = User::factory()->for($organization)->create([
        'name' => 'Pelanggan Anggrek',
        'email' => 'telepon@example.test',
        'whatsapp_phone' => '628777123456',
    ]);
    User::factory()->for($otherOrganization)->create([
        'name' => 'Pelanggan Melati Lain',
    ]);

    foreach ([$byName, $byEmail, $byWhatsapp] as $customer) {
        $customer->assignRole(RoleName::Customer->value);
    }

    $searches = [
        ['Melati', $byName->id],
        ['email-khusus', $byEmail->id],
        ['777123456', $byWhatsapp->id],
    ];

    foreach ($searches as [$search, $expectedUserId]) {
        $this->actingAs($owner)
            ->get(route('cms.users.index', ['search' => $search]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/Users')
                ->has('users', 1)
                ->where('users.0.id', $expectedUserId)
                ->where('pagination.total', 1)
                ->where('filters.search', $search)
                ->where('statistics.total', 4)
            );
    }
});

it('preserves the user search while paginating filtered results', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    for ($index = 1; $index <= 26; $index++) {
        $customer = User::factory()->for($organization)->create([
            'name' => sprintf('Needle Customer %02d', $index),
        ]);
        $customer->assignRole(RoleName::Customer->value);
    }

    $this->actingAs($owner)
        ->get(route('cms.users.index', ['search' => 'Needle', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Users')
            ->has('users', 1)
            ->where('pagination.current_page', 2)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 26)
            ->where('statistics.total', 27)
            ->where('filters.search', 'Needle')
        );
});
