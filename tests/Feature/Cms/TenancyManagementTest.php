<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows a superadmin to create an organization', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'slug' => 'karuhun-laundry',
        'is_active' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Karuhun Laundry',
        'slug' => 'karuhun-laundry',
    ]);
});

it('rejects an organization slug that duplicates after normalization', function (): void {
    Organization::factory()->create(['slug' => 'karuhun-laundry']);

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry Baru',
        'slug' => 'Karuhun Laundry',
        'is_active' => true,
    ])->assertSessionHasErrors('slug');

    expect(Organization::query()->count())->toBe(1);
});

it('rejects a generated organization slug that already exists', function (): void {
    Organization::factory()->create(['slug' => 'karuhun-laundry']);

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'slug' => '',
        'is_active' => true,
    ])->assertSessionHasErrors('slug');

    expect(Organization::query()->count())->toBe(1);
});

it('renders organization management only for superadmins', function (): void {
    $organization = Organization::factory()->create();
    Branch::factory()->for($organization)->create();
    User::factory()->for($organization)->create();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)
        ->get(route('cms.organizations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Organizations')
            ->has('organizations', 1)
            ->where('organizations.0.id', $organization->id)
            ->where('organizations.0.branches_count', 1)
            ->where('organizations.0.users_count', 1));

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('cms.organizations.index'))
        ->assertForbidden();
});

it('allows an owner to create cities and branches only in their organization', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherCity = City::factory()->for($otherOrganization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.cities.store'), [
        'name' => 'Bandung',
        'province' => 'Jawa Barat',
    ])->assertRedirect();

    $city = City::query()->where('organization_id', $organization->id)->firstOrFail();

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $city->id,
        'name' => 'Cabang Dago',
        'code' => 'BDG01',
        'phone' => '081234567890',
        'address' => 'Jl. Dago No. 1',
        'latitude' => -6.8781,
        'longitude' => 107.5953,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3500,
    ])->assertRedirect();

    $this->assertDatabaseHas('branches', [
        'organization_id' => $organization->id,
        'city_id' => $city->id,
        'code' => 'BDG01',
        'latitude' => -6.8781,
        'longitude' => 107.5953,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3500,
    ]);

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $otherCity->id,
        'name' => 'Cabang Silang',
        'code' => 'BAD01',
        'address' => 'Alamat lain',
    ])->assertSessionHasErrors('city_id');

    $this->assertDatabaseMissing('branches', ['code' => 'BAD01']);
});

it('rejects a branch code that duplicates after normalization', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->for($organization)->create();
    Branch::factory()->for($organization)->for($city)->create(['code' => 'BDG01']);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $city->id,
        'name' => 'Cabang Dago Baru',
        'code' => 'bdg01',
        'address' => 'Jl. Dago No. 2',
    ])->assertSessionHasErrors('code');

    expect(Branch::query()->count())->toBe(1);
});

it('scopes the branch management page to an owner organization', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($city)->create();
    Branch::factory()->create();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('cms.branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Branches')
            ->has('branches', 1)
            ->where('branches.0.id', $branch->id)
            ->has('cities', 1)
            ->where('cities.0.id', $city->id)
            ->has('organizations', 0));
});

it('lets a superadmin view branches across organizations', function (): void {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    Branch::factory()->for($firstOrganization)->create();
    Branch::factory()->for($secondOrganization)->create();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)
        ->get(route('cms.branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Branches')
            ->has('branches', 2)
            ->has('cities', 2)
            ->has('organizations', 2));
});

it('rejects branch assignments from another organization', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Admin Cabang',
        'email' => 'admin@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::BranchAdmin->value,
        'branch_ids' => [$branch->id, $otherBranch->id],
    ])->assertSessionHasErrors('branch_ids.1');

    $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
});

it('creates a branch user with scoped access', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Admin Kemang',
        'email' => 'kemang@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::BranchAdmin->value,
        'branch_ids' => [$branch->id],
    ])->assertRedirect();

    $user = User::query()->where('email', 'kemang@example.test')->firstOrFail();

    expect($user->organization_id)->toBe($organization->id)
        ->and($user->hasRole(RoleName::BranchAdmin->value))->toBeTrue()
        ->and($user->branches()->pluck('branches.id')->all())->toBe([$branch->id]);
});

it('requires branch access only for branch administrators', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Admin Tanpa Cabang',
        'email' => 'tanpa-cabang@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::BranchAdmin->value,
        'branch_ids' => [],
    ])->assertSessionHasErrors('branch_ids');

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Pelanggan Baru',
        'email' => 'pelanggan@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::Customer->value,
        'branch_ids' => [$branch->id],
    ])->assertSessionHasErrors('branch_ids');
});

it('allows only superadmins to create owners', function (): void {
    $organization = Organization::factory()->create();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Owner Kedua',
        'email' => 'owner-kedua@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::Owner->value,
    ])->assertSessionHasErrors('role');

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.users.store'), [
        'organization_id' => $organization->id,
        'name' => 'Owner Platform',
        'email' => 'owner-platform@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::Owner->value,
    ])->assertRedirect();

    $createdOwner = User::query()->where('email', 'owner-platform@example.test')->firstOrFail();

    expect($createdOwner->organization_id)->toBe($organization->id)
        ->and($createdOwner->hasRole(RoleName::Owner->value))->toBeTrue();

    $this->assertDatabaseMissing('users', ['email' => 'owner-kedua@example.test']);
});

it('scopes the user management page and excludes superadmins', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    Branch::factory()->for($otherOrganization)->create();

    $managedUser = User::factory()->for($organization)->create();
    $managedUser->assignRole(RoleName::BranchAdmin->value);
    $managedUser->branches()->attach($branch);

    $otherUser = User::factory()->for($otherOrganization)->create();
    $otherUser->assignRole(RoleName::Owner->value);

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('cms.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Users')
            ->has('users', 2)
            ->where('users.0.organization_id', $organization->id)
            ->where('users.1.organization_id', $organization->id)
            ->has('branches', 1)
            ->has('organizations', 0));

    $this->actingAs($superadmin)
        ->get(route('cms.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Users')
            ->has('users', 3)
            ->has('branches', 2)
            ->has('organizations', 2));
});

it('shares authenticated roles for role-aware navigation', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', [RoleName::Owner->value]));
});

it('forbids customers from tenant management', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->actingAs($customer)->post(route('cms.cities.store'), [
        'name' => 'Jakarta',
        'province' => 'DKI Jakarta',
    ])->assertForbidden();

    $this->actingAs($customer)
        ->get(route('cms.branches.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('cms.users.index'))
        ->assertForbidden();
});
