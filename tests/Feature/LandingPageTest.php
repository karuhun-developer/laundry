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

it('renders the public landing page for guests', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('Landing'));
});

it('redirects every authenticated role from the landing page to its workspace', function (
    RoleName $role,
    string $routeName,
): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();
    $user->assignRole($role->value);

    $request = $this->actingAs($user);

    if ($role === RoleName::Customer) {
        $request = $request->withSession(['customer_session_version' => $user->session_version]);
    }

    $request->get(route('home'))->assertRedirect(route($routeName));
})->with([
    [RoleName::Customer, 'mobile.app'],
    [RoleName::BranchAdmin, 'mobile.app'],
    [RoleName::Owner, 'cms.dashboard'],
    [RoleName::Superadmin, 'cms.dashboard'],
]);

it('renders complete landing SEO metadata in Blade without SSR', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('<title>LaundryHub | Aplikasi Laundry, Antar-Jemput, QRIS &amp; Multi-Cabang</title>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
        ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('<meta property="og:image"', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@type":"SoftwareApplication"', false)
        ->assertDontSee('data-server-rendered="true"', false);
});

it('marks application pages noindex without leaking landing metadata', function (): void {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertDontSee('<link rel="canonical"', false)
        ->assertDontSee('<script type="application/ld+json">', false);
});
