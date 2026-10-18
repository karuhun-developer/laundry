<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

const CMS_BRANCH_STATIC_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('generates an organization slug from its name', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'qris_api_key' => 'qris_test_organization_key',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Karuhun Laundry',
        'slug' => 'karuhun-laundry',
    ]);
});

it('allows duplicate organization names with unique generated slugs', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'qris_api_key' => 'qris_test_organization_key',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'qris_api_key' => 'qris_test_organization_key',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Organization::query()->orderBy('id')->pluck('slug')->all())->toBe([
        'karuhun-laundry',
        'karuhun-laundry-1',
    ]);
});

it('ignores a supplied organization slug', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Karuhun Laundry',
        'qris_api_key' => 'qris_test_organization_key',
        'slug' => 'slug-yang-tidak-boleh-dipakai',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Karuhun Laundry',
        'slug' => 'karuhun-laundry',
    ]);
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

it('allows an owner to create global cities and use any city for their branches', function (): void {
    $organization = Organization::factory()->create();
    $otherCity = City::factory()->create(['name' => 'Jakarta Selatan']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.cities.store'), [
        'name' => 'Bandung',
        'province' => 'Jawa Barat',
    ])->assertRedirect();

    $city = City::query()->where('name', 'Bandung')->firstOrFail();

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
        'name' => 'Cabang Kemang',
        'code' => 'JKT01',
        'address' => 'Alamat lain',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('branches', [
        'organization_id' => $organization->id,
        'city_id' => $otherCity->id,
        'code' => 'JKT01',
    ]);
});

it('validates and stores encrypted QRIS settings for a branch', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $city->id,
        'name' => 'Cabang QRIS',
        'code' => 'QRS01',
        'address' => 'Jl. Pembayaran No. 1',
        'qris_enabled' => true,
        'qris_api_key' => 'qris_custom_branch_key',
        'qris_base_url' => 'https://qris.karuhundeveloper.com',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $branch = Branch::query()->where('code', 'QRS01')->firstOrFail();

    expect($branch->qris_enabled)->toBeTrue()
        ->and($branch->qris_api_key)->toBe('qris_custom_branch_key')
        ->and($branch->qris_base_url)->toBe('https://qris.karuhundeveloper.com');

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $city->id,
        'name' => 'Cabang Tanpa Custom Key',
        'code' => 'QRS02',
        'address' => 'Jl. Pembayaran No. 2',
        'qris_enabled' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $branch2 = Branch::query()->where('code', 'QRS02')->firstOrFail();
    expect($branch2->qris_enabled)->toBeTrue()
        ->and($branch2->qris_api_key)->toBeNull();
});

it('enforces city uniqueness globally by name and province', function (): void {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $firstOwner = User::factory()->for($firstOrganization)->create();
    $secondOwner = User::factory()->for($secondOrganization)->create();
    $firstOwner->assignRole(RoleName::Owner->value);
    $secondOwner->assignRole(RoleName::Owner->value);

    $this->actingAs($firstOwner)->post(route('cms.cities.store'), [
        'name' => 'Bandung',
        'province' => 'Jawa Barat',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($secondOwner)->post(route('cms.cities.store'), [
        'name' => 'Bandung',
        'province' => 'Jawa Barat',
    ])->assertSessionHasErrors('name');

    $this->assertDatabaseCount('cities', 1);
});

it('rejects a branch code that duplicates after normalization', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->create();
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
    $city = City::factory()->create(['name' => 'A Jakarta']);
    $otherCity = City::factory()->create(['name' => 'Z Bandung']);
    $branch = Branch::factory()->for($organization)->for($city)->create([
        'qris_enabled' => true,
        'qris_api_key' => 'qris_test_key_sample',
    ]);
    Branch::factory()->for($otherCity)->create();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('cms.branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Branches')
            ->has('branches', 1)
            ->where('branches.0.id', $branch->id)
            ->where('branches.0.qris_enabled', true)
            ->where('branches.0.qris_api_key', 'qris_test_key_sample')
            ->has('cities', 2)
            ->where('cities.0.id', $city->id)
            ->has('organizations', 0));
});

it('lets a superadmin view branches across organizations', function (): void {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $firstCity = City::factory()->create(['name' => 'A Jakarta']);
    Branch::factory()->for($firstOrganization)->for($firstCity)->create();
    $secondCity = City::factory()->create(['name' => 'Z Bandung']);
    Branch::factory()->for($secondOrganization)->for($secondCity)->create();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)
        ->get(route('cms.branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Branches')
            ->has('branches', 2)
            ->has('cities', 2)
            ->where('cities.0.id', $firstCity->id)
            ->missing('cities.0.organization_id')
            ->missing('cities.0.organization')
            ->has('organizations', 2));
});

it('forbids an owner without an organization from tenant management pages', function (string $routeName): void {
    $owner = User::factory()->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'branches' => 'cms.branches.index',
    'users' => 'cms.users.index',
]);

it('forbids an owner without an organization from creating tenant records', function (string $resource): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $owner->assignRole(RoleName::Owner->value);

    [$routeName, $payload] = match ($resource) {
        'city' => ['cms.cities.store', [
            'organization_id' => $organization->id,
            'name' => 'Jakarta',
            'province' => 'DKI Jakarta',
        ]],
        'branch' => ['cms.branches.store', [
            'organization_id' => $organization->id,
            'city_id' => $city->id,
            'name' => 'Cabang Silang',
            'code' => 'BAD01',
            'address' => 'Alamat lain',
        ]],
        'user' => ['cms.users.store', [
            'organization_id' => $organization->id,
            'name' => 'Admin Silang',
            'email' => 'admin-silang@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => RoleName::BranchAdmin->value,
            'branch_ids' => [$branch->id],
        ]],
    };

    $this->actingAs($owner)
        ->post(route($routeName), $payload)
        ->assertForbidden();
})->with(['city', 'branch', 'user']);

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

it('allows superadmins to create owners with empty branch_ids as submitted by the cms form', function (): void {
    $organization = Organization::factory()->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.users.store'), [
        'organization_id' => $organization->id,
        'name' => 'Owner Utama',
        'email' => 'owner-utama@example.test',
        'whatsapp_phone' => '081234567890',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::Owner->value,
        'branch_ids' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $createdOwner = User::query()->where('email', 'owner-utama@example.test')->firstOrFail();

    expect($createdOwner->organization_id)->toBe($organization->id)
        ->and($createdOwner->hasRole(RoleName::Owner->value))->toBeTrue()
        ->and($createdOwner->branches)->toBeEmpty();
});

it('allows creating customers with empty branch_ids as submitted by the cms form', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.users.store'), [
        'name' => 'Pelanggan Walkin',
        'email' => 'walkin@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::Customer->value,
        'branch_ids' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $customer = User::query()->where('email', 'walkin@example.test')->firstOrFail();

    expect($customer->organization_id)->toBe($organization->id)
        ->and($customer->hasRole(RoleName::Customer->value))->toBeTrue()
        ->and($customer->branches)->toBeEmpty();
});

it('prohibits branch assignment when creating an owner account', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.users.store'), [
        'organization_id' => $organization->id,
        'name' => 'Owner Ilegal Cabang',
        'email' => 'owner-ilegal@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => RoleName::Owner->value,
        'branch_ids' => [$branch->id],
    ])->assertSessionHasErrors('branch_ids');

    $this->assertDatabaseMissing('users', ['email' => 'owner-ilegal@example.test']);
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
    $owner = User::factory()->for(Organization::factory())->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('cms.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', [RoleName::Owner->value]));
});

it('forbids customers from tenant management', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->actingAs($customer)
        ->withSession(['customer_session_version' => $customer->session_version])
        ->post(route('cms.cities.store'), [
            'name' => 'Jakarta',
            'province' => 'DKI Jakarta',
        ])->assertForbidden();

    $this->actingAs($customer)
        ->withSession(['customer_session_version' => $customer->session_version])
        ->get(route('cms.branches.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->withSession(['customer_session_version' => $customer->session_version])
        ->get(route('cms.users.index'))
        ->assertForbidden();
});
