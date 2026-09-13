<?php

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

it('redirects guests to login before opening the mobile app', function (): void {
    $this->get(route('mobile.app'))->assertRedirect(route('login'));
});

it('renders the mobile app for customers and branch admins', function (RoleName $role): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('mobile.app'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('mobile/App')
            ->where('auth.user.roles.0', $role->value));
})->with([RoleName::Customer, RoleName::BranchAdmin]);

it('keeps owner and superadmin accounts in the cms', function (RoleName $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('mobile.app'))
        ->assertRedirect(route('dashboard'));
})->with([RoleName::Owner, RoleName::Superadmin]);

it('redirects operational roles from dashboard to the mobile app', function (RoleName $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('mobile.app'));
})->with([RoleName::Customer, RoleName::BranchAdmin]);
