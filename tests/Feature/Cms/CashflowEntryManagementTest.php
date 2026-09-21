<?php

use App\Actions\Cashflow\GenerateDefaultCashflowCategories;
use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\TransitionPaymentStatus;
use App\Data\CreatePaymentData;
use App\Data\TransitionPaymentStatusData;
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

it('automatically records a debit cashflow entry when a QRIS payment transitions to paid', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_static_payload' => 'DUMMY_PAYLOAD',
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
