<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

const PAYMENT_TEST_STATIC_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('creates and manually reconciles a dynamic QRIS payment with audit history', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_static_payload' => PAYMENT_TEST_STATIC_QRIS,
    ]);
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'total_rupiah' => 50000,
        'items_subtotal_rupiah' => 50000,
    ]);
    Sanctum::actingAs($admin);

    $response = $this->postJson(route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]), [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 50000,
        'note' => 'Menunggu scan customer',
    ])->assertCreated()
        ->assertJsonPath('data.method', PaymentMethod::Qris->value)
        ->assertJsonPath('data.status', PaymentStatus::Pending->value)
        ->assertJsonPath('data.amount_rupiah', 50000)
        ->assertJsonPath('data.status_history.0.status', PaymentStatus::Pending->value);

    $payload = $response->json('data.qris_payload');
    expect($payload)->toBeString()->toContain('010212')->toContain('540550000');

    $imageDataUri = $response->json('data.qris_image_data_uri');
    expect($imageDataUri)->toBeString()->toStartWith('data:image/svg+xml;base64,');
    assert(is_string($imageDataUri));

    $svg = base64_decode(Str::after($imageDataUri, 'data:image/svg+xml;base64,'), true);
    expect($svg)->toBeString()
        ->toContain('<svg')
        ->toContain('width="360"')
        ->toContain('height="360"');

    $payment = Payment::query()->findOrFail($response->json('data.id'));

    $this->postJson(route('api.v1.organizations.branches.orders.payments.status-events.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
        'payment' => $payment,
    ]), [
        'status' => PaymentStatus::Paid->value,
        'note' => 'Dikonfirmasi dari mutasi QRIS',
    ])->assertOk()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.status_history.1.status', PaymentStatus::Paid->value)
        ->assertJsonCount(2, 'data.status_history');

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::Paid->value,
        'amount_rupiah' => 50000,
    ]);
    $this->assertDatabaseHas('activity_log', [
        'subject_type' => Payment::class,
        'subject_id' => $payment->id,
        'description' => 'payment status transitioned',
    ]);
});

it('records cash as paid immediately and rejects overpayment', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'total_rupiah' => 30000,
        'items_subtotal_rupiah' => 30000,
    ]);
    Sanctum::actingAs($admin);
    $route = route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);

    $this->postJson($route, [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 20000,
    ])->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.qris_image_data_uri', null);

    $this->postJson($route, [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 11000,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_CANNOT_BE_CREATED');
});

it('rejects duplicate pending payments and invalid reconciliation transitions', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_static_payload' => PAYMENT_TEST_STATIC_QRIS,
    ]);
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create();
    Sanctum::actingAs($admin);
    $route = route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);

    $first = $this->postJson($route, [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 12000,
    ])->assertCreated();

    $this->postJson($route, [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 12000,
    ])->assertUnprocessable();

    $payment = Payment::query()->findOrFail($first->json('data.id'));
    $statusRoute = route('api.v1.organizations.branches.orders.payments.status-events.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
        'payment' => $payment,
    ]);

    $this->postJson($statusRoute, ['status' => PaymentStatus::Failed->value])->assertOk();
    $this->postJson($statusRoute, ['status' => PaymentStatus::Paid->value])
        ->assertConflict()
        ->assertJsonPath('code', 'INVALID_PAYMENT_STATUS_TRANSITION');
});

it('enforces branch assignment and customer read-only boundaries', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $order = Order::factory()->for($organization)->for($branch)->create();
    $route = route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);

    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);
    $this->postJson($route, [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 12000,
    ])->assertForbidden();

    $unassignedAdmin = User::factory()->for($organization)->create();
    $unassignedAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($unassignedAdmin);
    $this->postJson($route, [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 12000,
    ])->assertForbidden();
});
