<?php

use App\Actions\Cashflow\GenerateDefaultCashflowCategories;
use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\TransitionPaymentStatus;
use App\Data\CreatePaymentData;
use App\Data\TransitionPaymentStatusData;
use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('renders the cashflow page with statement and entries for owner', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $category = CashflowCategory::query()
        ->where('organization_id', $organization->id)
        ->where('type', CashflowType::Credit)
        ->firstOrFail();

    CashflowEntry::factory()->for($organization)->create([
        'branch_id' => $branch->id,
        'cashflow_category_id' => $category->id,
        'type' => CashflowType::Credit,
        'amount_rupiah' => 500000,
        'entry_date' => now()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get(route('cms.cashflow.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Cashflow')
            ->has('statement')
            ->has('entries')
            ->has('branches')
            ->has('categories')
            ->where('entries.0.entry_date', now()->toDateString())
            ->where('statement.total_credit', 500000)
        );
});

it('allows an owner to record a manual cashflow expense', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $category = CashflowCategory::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Listrik')
        ->firstOrFail();

    $this->actingAs($owner)->post(route('cms.cashflow.store'), [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'cashflow_category_id' => $category->id,
        'type' => CashflowType::Credit->value,
        'amount_rupiah' => 350000,
        'description' => 'Bayar listrik bulan ini',
        'entry_date' => now()->toDateString(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('cashflow_entries', [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'cashflow_category_id' => $category->id,
        'amount_rupiah' => 350000,
        'description' => 'Bayar listrik bulan ini',
    ]);
});

it('allows an owner to delete manual cashflow entries', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $entry = CashflowEntry::factory()->for($organization)->for($branch)->create([
        'payment_id' => null,
    ]);

    $this->actingAs($owner)->delete(route('cms.cashflow.destroy', $entry))
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('cashflow_entries', ['id' => $entry->id]);
});

it('allows an owner to update only the amount and date of manual income', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $category = CashflowCategory::factory()->for($organization)->debit()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $entry = CashflowEntry::factory()->for($organization)->for($branch)->create([
        'cashflow_category_id' => $category->id,
        'type' => CashflowType::Debit,
        'amount_rupiah' => 125000,
        'description' => 'Pemasukan manual',
        'entry_date' => '2026-10-01',
        'payment_id' => null,
    ]);

    $this->actingAs($owner)->patch(route('cms.cashflow.update', $entry), [
        'amount_rupiah' => 175000,
        'entry_date' => '2026-10-18',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('cashflow_entries', [
        'id' => $entry->id,
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'cashflow_category_id' => $category->id,
        'type' => CashflowType::Debit->value,
        'amount_rupiah' => 175000,
        'description' => 'Pemasukan manual',
        'entry_date' => '2026-10-18 00:00:00',
    ]);
});

it('rejects protected fields when updating manual income', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $category = CashflowCategory::factory()->for($organization)->debit()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $entry = CashflowEntry::factory()->for($organization)->for($branch)->create([
        'cashflow_category_id' => $category->id,
        'type' => CashflowType::Debit,
        'amount_rupiah' => 125000,
        'description' => 'Pemasukan manual',
        'payment_id' => null,
    ]);

    $this->actingAs($owner)->patch(route('cms.cashflow.update', $entry), [
        'amount_rupiah' => 175000,
        'description' => 'Deskripsi yang tidak boleh berubah',
        'entry_date' => '2026-10-18',
    ])->assertSessionHasErrors('description');

    $this->assertDatabaseHas('cashflow_entries', [
        'id' => $entry->id,
        'amount_rupiah' => 125000,
        'description' => 'Pemasukan manual',
    ]);
});

it('allows an owner to update every field of a manual expense', function (): void {
    $organization = Organization::factory()->create();
    $originalBranch = Branch::factory()->for($organization)->create();
    $newBranch = Branch::factory()->for($organization)->create();
    $originalCategory = CashflowCategory::factory()->for($organization)->credit()->create();
    $newCategory = CashflowCategory::factory()->for($organization)->debit()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $entry = CashflowEntry::factory()->for($organization)->for($originalBranch)->create([
        'cashflow_category_id' => $originalCategory->id,
        'type' => CashflowType::Credit,
        'amount_rupiah' => 350000,
        'description' => 'Pengeluaran lama',
        'entry_date' => '2026-10-01',
        'payment_id' => null,
    ]);

    $this->actingAs($owner)->patch(route('cms.cashflow.update', $entry), [
        'organization_id' => $organization->id,
        'branch_id' => $newBranch->id,
        'cashflow_category_id' => $newCategory->id,
        'type' => CashflowType::Debit->value,
        'amount_rupiah' => 425000,
        'description' => 'Entri yang sudah diperbarui',
        'entry_date' => '2026-10-18',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('cashflow_entries', [
        'id' => $entry->id,
        'organization_id' => $organization->id,
        'branch_id' => $newBranch->id,
        'cashflow_category_id' => $newCategory->id,
        'type' => CashflowType::Debit->value,
        'amount_rupiah' => 425000,
        'description' => 'Entri yang sudah diperbarui',
        'entry_date' => '2026-10-18 00:00:00',
    ]);
});

it('forbids updating an auto-generated payment cashflow entry', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    $payment = Payment::factory()->for($organization)->for($branch)->create();

    $entry = CashflowEntry::factory()->for($organization)->for($branch)->create([
        'payment_id' => $payment->id,
        'type' => CashflowType::Debit,
        'amount_rupiah' => 50000,
    ]);

    $this->actingAs($owner)->patch(route('cms.cashflow.update', $entry), [
        'amount_rupiah' => 75000,
        'entry_date' => '2026-10-18',
    ])->assertForbidden();

    $this->assertDatabaseHas('cashflow_entries', [
        'id' => $entry->id,
        'amount_rupiah' => 50000,
    ]);
});

it('forbids updating another organizations manual cashflow entry', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    $otherBranch = Branch::factory()->for($otherOrganization)->create();

    $entry = CashflowEntry::factory()->for($otherOrganization)->for($otherBranch)->create([
        'payment_id' => null,
    ]);

    $this->actingAs($owner)->patch(route('cms.cashflow.update', $entry), [
        'amount_rupiah' => 75000,
        'entry_date' => '2026-10-18',
    ])->assertForbidden();
});

it('forbids deleting an auto-generated payment cashflow entry', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $order = Order::factory()->for($organization)->for($branch)->create([
        'total_rupiah' => 50000,
        'priced_at' => now(),
    ]);

    $payment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 50000,
    ]);

    $entry = CashflowEntry::factory()->for($organization)->for($branch)->create([
        'payment_id' => $payment->id,
    ]);

    $this->actingAs($owner)->delete(route('cms.cashflow.destroy', $entry))
        ->assertForbidden();

    $this->assertDatabaseHas('cashflow_entries', ['id' => $entry->id]);
});

it('keeps payment cashflow entries read-only for superadmin', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);
    $payment = Payment::factory()->for($organization)->for($branch)->create();

    $entry = CashflowEntry::factory()->for($organization)->for($branch)->create([
        'payment_id' => $payment->id,
        'type' => CashflowType::Debit,
        'amount_rupiah' => 50000,
    ]);

    $this->actingAs($superadmin)->patch(route('cms.cashflow.update', $entry), [
        'amount_rupiah' => 75000,
        'entry_date' => '2026-10-18',
    ])->assertForbidden();

    $this->actingAs($superadmin)->delete(route('cms.cashflow.destroy', $entry))
        ->assertForbidden();

    $this->assertDatabaseHas('cashflow_entries', [
        'id' => $entry->id,
        'amount_rupiah' => 50000,
    ]);
});

it('automatically records a debit cashflow entry when a QRIS payment transitions to paid', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
    ]);
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $staff = User::factory()->for($organization)->create();
    $staff->assignRole(RoleName::BranchAdmin->value);
    $staff->branches()->attach($branch);

    $order = Order::factory()->for($organization)->for($branch)->create([
        'total_rupiah' => 75000,
        'priced_at' => now(),
        'order_number' => 'ORD-TEST-001',
    ]);

    $payment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 75000,
    ]);

    app(TransitionPaymentStatus::class)->handle(
        order: $order,
        payment: $payment,
        actor: $staff,
        data: new TransitionPaymentStatusData(
            status: PaymentStatus::Paid,
            note: 'QRIS sudah masuk ke rekening cabang.',
        ),
    );

    $this->assertDatabaseHas('cashflow_entries', [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'payment_id' => $payment->id,
        'type' => CashflowType::Debit->value,
        'amount_rupiah' => 75000,
        'description' => 'Pembayaran pesanan #ORD-TEST-001',
    ]);
});

it('automatically records a debit cashflow entry when cash payment is received', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $staff = User::factory()->for($organization)->create();
    $staff->assignRole(RoleName::BranchAdmin->value);
    $staff->branches()->attach($branch);

    $order = Order::factory()->for($organization)->for($branch)->create([
        'total_rupiah' => 100000,
        'priced_at' => now(),
        'order_number' => 'ORD-CASH-002',
    ]);

    $payment = app(CreatePayment::class)->handle(
        order: $order,
        branch: $branch,
        actor: $staff,
        data: new CreatePaymentData(
            method: PaymentMethod::Cash,
            amountRupiah: 100000,
            note: 'Uang pas diterima.',
            gatewayDriver: null,
            gatewayMethod: null,
        ),
    );

    $this->assertDatabaseHas('cashflow_entries', [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'payment_id' => $payment->id,
        'type' => CashflowType::Debit->value,
        'amount_rupiah' => 100000,
        'description' => 'Pembayaran pesanan #ORD-CASH-002',
    ]);
});

it('aggregates same-named categories across organizations in the cashflow statement', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $org1 = Organization::factory()->create(['name' => 'Org A']);
    $branch1 = Branch::factory()->for($org1)->create();
    $cat1 = CashflowCategory::factory()->for($org1)->create([
        'name' => 'Pembayaran Pesanan',
        'type' => CashflowType::Debit,
        'group' => CashflowGroup::Rutin,
    ]);
    CashflowEntry::factory()->for($org1)->create([
        'branch_id' => $branch1->id,
        'cashflow_category_id' => $cat1->id,
        'type' => CashflowType::Debit,
        'amount_rupiah' => 28000,
        'entry_date' => now()->startOfMonth()->toDateString(),
    ]);

    $org2 = Organization::factory()->create(['name' => 'Org B']);
    $branch2 = Branch::factory()->for($org2)->create();
    $cat2 = CashflowCategory::factory()->for($org2)->create([
        'name' => 'Pembayaran Pesanan',
        'type' => CashflowType::Debit,
        'group' => CashflowGroup::Rutin,
    ]);
    CashflowEntry::factory()->for($org2)->create([
        'branch_id' => $branch2->id,
        'cashflow_category_id' => $cat2->id,
        'type' => CashflowType::Debit,
        'amount_rupiah' => 66000,
        'entry_date' => now()->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($superadmin)
        ->get(route('cms.cashflow.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cms/Cashflow')
            ->where('statement.total_debit', 94000)
            ->where('statement.categories', fn ($categories) => collect($categories)->where('name', 'Pembayaran Pesanan')->count() === 1
                && collect($categories)->firstWhere('name', 'Pembayaran Pesanan')['total'] === 94000
            )
        );
});
