<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PushNotificationEvent;
use App\Enums\RoleName;
use App\Jobs\SendOrderPushNotification;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

const PAYMENT_TEST_STATIC_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('rejects payment before pickup pricing is confirmed', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'status' => OrderStatus::PickupRequested,
        'items_subtotal_rupiah' => 0,
        'total_rupiah' => 0,
        'priced_at' => null,
    ]);
    Sanctum::actingAs($admin);

    $this->postJson(route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]), [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 12000,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_CANNOT_BE_CREATED')
        ->assertJsonPath('message', 'Harga pesanan belum dikonfirmasi oleh petugas.');

    $this->assertDatabaseCount('payments', 0);
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
    Queue::fake();

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
    Queue::assertPushed(
        SendOrderPushNotification::class,
        fn (SendOrderPushNotification $job): bool => $job->orderId === $order->id
            && $job->event === PushNotificationEvent::PaymentVerified,
    );

    $this->postJson(route('api.v1.organizations.branches.orders.payments.status-events.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
        'payment' => $payment,
    ]), [
        'status' => PaymentStatus::Paid->value,
    ])->assertConflict();

    Queue::assertPushed(SendOrderPushNotification::class, 1);
});

it('allows a customer to create QRIS only for their own order', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_static_payload' => PAYMENT_TEST_STATIC_QRIS,
    ]);
    $customer = User::factory()->withCustomerPin()->create();
    $customer->assignRole(RoleName::Customer->value);
    $ownOrder = Order::factory()->for($organization)->for($branch)->for($customer, 'customer')->create([
        'total_rupiah' => 27500,
        'items_subtotal_rupiah' => 27500,
    ]);
    $otherOrder = Order::factory()->for($organization)->for($branch)->create();
    Sanctum::actingAs($customer);

    $ownOrderRoute = route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $ownOrder,
    ]);

    $response = $this->postJson($ownOrderRoute, [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 27500,
    ])->assertCreated()
        ->assertJsonPath('data.method', PaymentMethod::Qris->value)
        ->assertJsonPath('data.status', PaymentStatus::Pending->value)
        ->assertJsonPath('data.amount_rupiah', 27500);

    expect($response->json('data.qris_payload'))
        ->toBeString()
        ->toContain('010212')
        ->toContain('540527500');
    expect($response->json('data.qris_image_data_uri'))
        ->toBeString()
        ->toStartWith('data:image/svg+xml;base64,');

    $cashOrder = Order::factory()->for($organization)->for($branch)->for($customer, 'customer')->create();

    $this->postJson(route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $cashOrder,
    ]), [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 12000,
    ])->assertForbidden();

    $this->postJson(route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $otherOrder,
    ]), [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 12000,
    ])->assertForbidden();
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

it('allows branch admin to accept cash payment when a dynamic QRIS is pending and cancels the pending QRIS', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_static_payload' => PAYMENT_TEST_STATIC_QRIS,
    ]);
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

    $qrisResponse = $this->postJson($route, [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 30000,
    ])->assertCreated();

    $qrisId = $qrisResponse->json('data.id');
    expect(Payment::find($qrisId)?->status)->toBe(PaymentStatus::Pending);

    $this->postJson($route, [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 30000,
        'note' => 'Pelanggan memilih bayar tunai di kasir',
    ])->assertCreated()
        ->assertJsonPath('data.method', PaymentMethod::Cash->value)
        ->assertJsonPath('data.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.amount_rupiah', 30000);

    $cancelledQris = Payment::query()->findOrFail($qrisId);
    expect($cancelledQris->status)->toBe(PaymentStatus::Cancelled);
    expect($cancelledQris->statusEvents()->where('status', PaymentStatus::Cancelled)->exists())->toBeTrue();
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
