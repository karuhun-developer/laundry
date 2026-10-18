<?php

use App\Actions\Cashflow\GenerateDefaultCashflowCategories;
use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Enums\RoleName;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('generates default cashflow categories when an organization is created', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.organizations.store'), [
        'name' => 'Laundry Baru',
        'qris_api_key' => 'qris_test_key_cat',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $organization = Organization::query()->where('name', 'Laundry Baru')->firstOrFail();

    $categories = CashflowCategory::query()->where('organization_id', $organization->id)->get();

    expect($categories)->toHaveCount(13);

    $paymentCategory = $categories->firstWhere('name', 'Pembayaran Pesanan');
    expect($paymentCategory)->not->toBeNull()
        ->and($paymentCategory->type)->toBe(CashflowType::Debit)
        ->and($paymentCategory->group)->toBe(CashflowGroup::Rutin)
        ->and($paymentCategory->is_system)->toBeTrue();
});

it('allows an owner to create custom cashflow categories in their organization', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.cashflow.categories.store'), [
        'name' => 'Bonus Karyawan',
        'type' => CashflowType::Credit->value,
        'group' => CashflowGroup::NonRutin->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('cashflow_categories', [
        'organization_id' => $organization->id,
        'name' => 'Bonus Karyawan',
        'type' => CashflowType::Credit->value,
        'group' => CashflowGroup::NonRutin->value,
        'is_system' => false,
    ]);
});

it('allows a superadmin to create custom cashflow categories for any organization', function (): void {
    $organization = Organization::factory()->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.cashflow.categories.store'), [
        'organization_id' => $organization->id,
        'name' => 'Penjualan Parfum Laundry',
        'type' => CashflowType::Debit->value,
        'group' => CashflowGroup::Rutin->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('cashflow_categories', [
        'organization_id' => $organization->id,
        'name' => 'Penjualan Parfum Laundry',
        'type' => CashflowType::Debit->value,
    ]);
});

it('forbids branch admins from creating cashflow categories', function (): void {
    $organization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);

    $this->actingAs($branchAdmin)->post(route('cms.cashflow.categories.store'), [
        'name' => 'Kategori Terlarang',
        'type' => CashflowType::Credit->value,
        'group' => CashflowGroup::Rutin->value,
    ])->assertForbidden();
});

it('strictly forbids updating the Pembayaran Pesanan category', function (): void {
    $organization = Organization::factory()->create();
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $category = CashflowCategory::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Pembayaran Pesanan')
        ->firstOrFail();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->patch(route('cms.cashflow.categories.update', $category), [
        'name' => 'Pembayaran Diubah',
        'group' => CashflowGroup::NonRutin->value,
    ])->assertForbidden();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->patch(route('cms.cashflow.categories.update', $category), [
        'name' => 'Pembayaran Diubah Oleh Superadmin',
        'group' => CashflowGroup::NonRutin->value,
    ])->assertForbidden();

    expect($category->refresh()->name)->toBe('Pembayaran Pesanan');
});

it('strictly forbids deleting the Pembayaran Pesanan category', function (): void {
    $organization = Organization::factory()->create();
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $category = CashflowCategory::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Pembayaran Pesanan')
        ->firstOrFail();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->delete(route('cms.cashflow.categories.destroy', $category))
        ->assertForbidden();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->delete(route('cms.cashflow.categories.destroy', $category))
        ->assertForbidden();

    $this->assertDatabaseHas('cashflow_categories', ['id' => $category->id]);
});

it('allows updating a custom cashflow category', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $category = CashflowCategory::factory()->for($organization)->create([
        'name' => 'Biaya Lama',
        'type' => CashflowType::Credit,
        'group' => CashflowGroup::Rutin,
        'is_system' => false,
    ]);

    $this->actingAs($owner)->patch(route('cms.cashflow.categories.update', $category), [
        'name' => 'Biaya Baru',
        'group' => CashflowGroup::NonRutin->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($category->refresh()->name)->toBe('Biaya Baru')
        ->and($category->group)->toBe(CashflowGroup::NonRutin);
});

it('allows deleting a custom category only if it has no entries', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $categoryWithoutEntries = CashflowCategory::factory()->for($organization)->create([
        'name' => 'Kategori Kosong',
        'is_system' => false,
    ]);

    $this->actingAs($owner)->delete(route('cms.cashflow.categories.destroy', $categoryWithoutEntries))
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('cashflow_categories', ['id' => $categoryWithoutEntries->id]);

    $categoryWithEntries = CashflowCategory::factory()->for($organization)->create([
        'name' => 'Kategori Berisi',
        'is_system' => false,
    ]);
    CashflowEntry::factory()->for($organization)->create([
        'cashflow_category_id' => $categoryWithEntries->id,
    ]);

    $this->actingAs($owner)->delete(route('cms.cashflow.categories.destroy', $categoryWithEntries))
        ->assertForbidden();

    $this->assertDatabaseHas('cashflow_categories', ['id' => $categoryWithEntries->id]);
});
