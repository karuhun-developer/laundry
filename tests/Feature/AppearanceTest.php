<?php

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

it('renders a light workspace before JavaScript runs despite a saved dark cookie', function (
    RoleName $role,
    string $routeName,
): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->withSession(['customer_session_version' => $user->session_version])
        ->withUnencryptedCookie('appearance', 'dark')
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('data-workspace-style="stitch"', false)
        ->assertSee('<meta name="color-scheme" content="light">', false)
        ->assertSee('<meta name="theme-color" content="#f8f9fb">', false)
        ->assertDontSee('class="dark"', false)
        ->assertDontSee('prefers-color-scheme', false);
})->with([
    'reporting dashboard' => [RoleName::Owner, 'cms.dashboard'],
    'branch management' => [RoleName::Owner, 'cms.branches.index'],
    'appearance settings' => [RoleName::Owner, 'appearance.edit'],
    'customer app' => [RoleName::Customer, 'mobile.app'],
    'branch admin app' => [RoleName::BranchAdmin, 'mobile.app'],
]);

it('keeps public entry pages light with their matching visual skin', function (string $routeName, string $skin): void {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('data-workspace-style="'.$skin.'"', false)
        ->assertSee('<meta name="color-scheme" content="light">', false)
        ->assertDontSee('class="dark"', false)
        ->assertDontSee('prefers-color-scheme', false);
})->with([
    'landing page' => ['home', 'default'],
    'login page' => ['login', 'stitch'],
    'registration page' => ['register', 'stitch'],
]);
