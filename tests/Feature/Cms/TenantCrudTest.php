<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('updates an organization and ignores its own normalized slug', function (): void {
    $organization = Organization::factory()->create(['slug' => 'karuhun-laundry']);
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->patch(route('cms.organizations.update', $organization), [
        'name' => 'Karuhun Laundry Utama',
        'slug' => 'Karuhun Laundry',
        'is_active' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Karuhun Laundry Utama',
        'slug' => 'karuhun-laundry',
        'is_active' => false,
    ]);
});

it('rejects another organization slug after normalization during update', function (): void {
    Organization::factory()->create(['slug' => 'existing-laundry']);
    $organization = Organization::factory()->create(['slug' => 'target-laundry']);
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->patch(route('cms.organizations.update', $organization), [
        'name' => 'Target Laundry',
        'slug' => 'Existing Laundry',
        'is_active' => true,
    ])->assertSessionHasErrors('slug');

    expect($organization->refresh()->slug)->toBe('target-laundry');
});

it('requires exact confirmation and an empty tenant before deleting an organization', function (): void {
    $organization = Organization::factory()->create(['name' => 'Tenant Berisi']);
    City::factory()->for($organization)->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->delete(route('cms.organizations.destroy', $organization), [
        'confirmation' => 'salah',
    ])->assertSessionHasErrors('confirmation');

    $this->actingAs($superadmin)->delete(route('cms.organizations.destroy', $organization), [
        'confirmation' => 'Tenant Berisi',
    ])->assertSessionHasErrors('confirmation');

    $this->assertDatabaseHas('organizations', ['id' => $organization->id]);

    $empty = Organization::factory()->create(['name' => 'Tenant Kosong']);

    $this->actingAs($superadmin)->delete(route('cms.organizations.destroy', $empty), [
        'confirmation' => 'Tenant Kosong',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('organizations', ['id' => $empty->id]);
});

it('forbids owners from updating or deleting organizations', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.organizations.update', $organization), [
        'name' => 'Tidak Boleh',
        'slug' => 'tidak-boleh',
        'is_active' => true,
    ])->assertForbidden();

    $this->actingAs($owner)->delete(route('cms.organizations.destroy', $organization), [
        'confirmation' => $organization->name,
    ])->assertForbidden();
});

it('lets an owner update cities and branches only inside their organization', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($city)->create(['code' => 'JKT01']);
    $otherBranch = Branch::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.cities.update', $city), [
        'name' => 'Jakarta Selatan',
        'province' => 'DKI Jakarta',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($owner)->patch(route('cms.branches.update', $branch), [
        'city_id' => $city->id,
        'name' => 'Cabang Kemang',
        'code' => 'kmg01',
        'phone' => '081200000000',
        'address' => 'Jl. Kemang Raya',
        'latitude' => -6.2607,
        'longitude' => 106.8136,
        'free_delivery_radius_meters' => 12000,
        'delivery_fee_per_km_rupiah' => 4000,
        'is_active' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('branches', [
        'id' => $branch->id,
        'name' => 'Cabang Kemang',
        'code' => 'KMG01',
        'is_active' => false,
    ]);

    $this->actingAs($owner)->patch(route('cms.branches.update', $otherBranch), [
        'city_id' => $otherBranch->city_id,
        'name' => 'Silang Tenant',
        'code' => $otherBranch->code,
        'address' => $otherBranch->address,
    ])->assertForbidden();
});

it('protects cities and branches that still have operational dependencies', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->for($organization)->create(['name' => 'Bandung']);
    $branch = Branch::factory()->for($organization)->for($city)->create(['name' => 'Cabang Dago']);
    Order::factory()->for($organization)->for($branch)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->delete(route('cms.cities.destroy', $city), [
        'confirmation' => 'Bandung',
    ])->assertSessionHasErrors('confirmation');

    $this->actingAs($owner)->delete(route('cms.branches.destroy', $branch), [
        'confirmation' => 'Cabang Dago',
    ])->assertSessionHasErrors('confirmation');

    $this->assertDatabaseHas('cities', ['id' => $city->id]);
    $this->assertDatabaseHas('branches', ['id' => $branch->id]);
});

it('deletes empty cities and branches after exact modal confirmation', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->for($organization)->create(['name' => 'Jakarta']);
    $branch = Branch::factory()->for($organization)->for($city)->create(['name' => 'Cabang Kosong']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->delete(route('cms.branches.destroy', $branch), [
        'confirmation' => 'Cabang Kosong',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($owner)->delete(route('cms.cities.destroy', $city), [
        'confirmation' => 'Jakarta',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    $this->assertDatabaseMissing('cities', ['id' => $city->id]);
});

it('updates user access while keeping branch assignments tenant scoped', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->create();
    $managedUser = User::factory()->for($organization)->create();
    $managedUser->assignRole(RoleName::Customer->value);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.users.update', $managedUser), [
        'name' => 'Admin Cabang Baru',
        'email' => 'admin-baru@example.test',
        'password' => '',
        'password_confirmation' => '',
        'role' => RoleName::BranchAdmin->value,
        'branch_ids' => [$branch->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($managedUser->refresh()->hasRole(RoleName::BranchAdmin->value))->toBeTrue()
        ->and($managedUser->branches()->pluck('branches.id')->all())->toBe([$branch->id]);

    $this->actingAs($owner)->patch(route('cms.users.update', $managedUser), [
        'name' => 'Admin Cabang Baru',
        'email' => 'admin-baru@example.test',
        'role' => RoleName::BranchAdmin->value,
        'branch_ids' => [$otherBranch->id],
    ])->assertSessionHasErrors('branch_ids.0');
});

it('keeps users without an organization limited to the customer role', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->patch(route('cms.users.update', $customer), [
        'name' => 'Pelanggan Umum',
        'email' => 'pelanggan-umum@example.test',
        'role' => RoleName::Owner->value,
    ])->assertSessionHasErrors('role');

    $this->actingAs($superadmin)->patch(route('cms.users.update', $customer), [
        'name' => 'Pelanggan Umum',
        'email' => 'pelanggan-umum@example.test',
        'role' => RoleName::Customer->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($customer->refresh()->organization_id)->toBeNull()
        ->and($customer->name)->toBe('Pelanggan Umum')
        ->and($customer->hasRole(RoleName::Customer->value))->toBeTrue();
});

it('prevents owners from managing owner accounts and users from deleting themselves', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.users.update', $owner), [
        'name' => $owner->name,
        'email' => $owner->email,
        'role' => RoleName::Customer->value,
        'branch_ids' => [],
    ])->assertForbidden();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->delete(route('cms.users.destroy', $superadmin), [
        'confirmation' => $superadmin->email,
    ])->assertSessionHasErrors('confirmation');

    $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
});

it('requires the exact email before deleting a managed user', function (): void {
    $organization = Organization::factory()->create();
    $managedUser = User::factory()->for($organization)->create(['email' => 'hapus@example.test']);
    $managedUser->assignRole(RoleName::Customer->value);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->delete(route('cms.users.destroy', $managedUser), [
        'confirmation' => 'salah@example.test',
    ])->assertSessionHasErrors('confirmation');

    $this->actingAs($owner)->delete(route('cms.users.destroy', $managedUser), [
        'confirmation' => 'hapus@example.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);
});
