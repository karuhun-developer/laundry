<?php

use App\Actions\Orders\StoreOrderEvidence;
use App\Enums\CashflowType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CashflowEntry;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function orderOperationsContext(OrderStatus $status = OrderStatus::Received): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create(['customer_id' => $customer->id, 'status' => $status]);

    return [$organization, $branch, $admin, $customer, $order];
}

function orderOperationsRoute(Order $order, string $resource): string
{
    return route('api.v1.organizations.branches.orders.'.$resource.'.store', [$order->organization, $order->branch, $order]);
}

it('paginates scoped orders beyond the former limit', function (): void {
    [$organization, $branch, $admin, $customer] = orderOperationsContext();
    Order::factory()->count(110)->for($organization)->for($branch)->create(['customer_id' => $customer->id]);
    Order::factory()->create();
    Sanctum::actingAs($admin);

    $this->getJson(route('api.v1.organizations.orders.index', [$organization, 'paginated' => 1, 'page' => 5]))
        ->assertOk()->assertJsonCount(11, 'data')->assertJsonPath('meta.total', 111);
});

it('filters unpaid and overdue orders before pagination', function (): void {
    [$organization, $branch, $admin, $customer, $order] = orderOperationsContext();
    $this->freezeTime();
    $order->update(['estimated_ready_at' => now()->subHour()]);
    Order::factory()->for($organization)->for($branch)->create(['status' => OrderStatus::ReadyForPickup, 'estimated_ready_at' => now()->subDay()]);
    Sanctum::actingAs($admin);

    $this->getJson(route('api.v1.organizations.orders.index', [$organization, 'paginated' => 1, 'unpaid' => 1, 'overdue' => 1]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $order->id)->assertJsonPath('data.0.is_overdue', true);
});

it('records an authorized estimate override and its reason', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'estimate'), ['estimated_ready_at' => now()->addDay()->toIso8601String(), 'reason' => 'Mesin perlu perawatan'])
        ->assertOk()->assertJsonCount(1, 'data.status_history');
    expect($order->fresh()->estimated_ready_at)->not->toBeNull();
    $this->assertDatabaseHas('order_status_events', ['order_id' => $order->id, 'actor_id' => $admin->id]);
});

it('blocks delivery departure until laundry is ready', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $task = $order->tasks()->create(['kind' => 'delivery', 'status' => 'scheduled', 'operator_name' => 'Budi', 'scheduled_at' => now(), 'address' => 'Jalan Mawar']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'tasks'), ['task_id' => $task->id, 'kind' => 'delivery', 'status' => 'en_route'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('order_tasks', ['id' => $task->id, 'status' => 'scheduled']);
});

it('preserves the requested instant when an estimate is submitted in UTC', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $this->freezeTime();
    $requested = now()->addDay()->setTimezone('UTC');
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'estimate'), ['estimated_ready_at' => $requested->toIso8601String(), 'reason' => 'Penyesuaian jadwal'])
        ->assertOk();

    expect($order->fresh()->estimated_ready_at->timestamp)->toBe($requested->timestamp);
});

it('preserves the requested instant when a task schedule is submitted in UTC', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $this->freezeTime();
    $requested = now()->addDay()->setTimezone('UTC');
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'tasks'), ['kind' => 'delivery', 'status' => 'scheduled', 'operator_name' => 'Budi',
        'scheduled_at' => $requested->toIso8601String(), 'address' => 'Jalan Mawar'])
        ->assertOk();

    expect($order->tasks()->firstOrFail()->scheduled_at->timestamp)->toBe($requested->timestamp);
});

it('completes delivery and order atomically with a recipient', function (): void {
    [, , $admin, , $order] = orderOperationsContext(OrderStatus::ReadyForPickup);
    $task = $order->tasks()->create(['kind' => 'delivery', 'status' => 'en_route']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'tasks'), ['task_id' => $task->id, 'kind' => 'delivery', 'status' => 'completed', 'recipient_name' => 'Sari'])
        ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.tasks.0.recipient_name', 'Sari');
    $this->assertDatabaseHas('order_tasks', ['id' => $task->id, 'status' => 'completed']);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
});

it('prevents completion from bypassing an active delivery', function (): void {
    [, , $admin, , $order] = orderOperationsContext(OrderStatus::ReadyForPickup);
    $order->tasks()->create(['kind' => 'delivery', 'status' => 'scheduled']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'status-events'), ['status' => 'completed'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready_for_pickup']);
});

it('cancels unfinished tasks when the order is cancelled', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $task = $order->tasks()->create(['kind' => 'pickup', 'status' => 'waiting']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'status-events'), ['status' => 'cancelled'])->assertOk();
    $this->assertDatabaseHas('order_tasks', ['id' => $task->id, 'status' => 'cancelled']);
    $this->assertDatabaseHas('order_operation_events', ['order_task_id' => $task->id, 'status' => 'cancelled']);
});

it('allows the owning customer to create a complaint with private evidence', function (): void {
    Storage::fake('local');
    [, , , $customer, $order] = orderOperationsContext();
    Sanctum::actingAs($customer);

    $this->post(orderOperationsRoute($order, 'complaints'), ['category' => 'damaged', 'description' => 'Kancing rusak', 'photos' => [UploadedFile::fake()->image('bukti.jpg')]], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.complaints.0.status', 'new');
    $media = $order->complaints()->firstOrFail()->getFirstMedia('evidence');
    expect($media->disk)->toBe('local');
    $this->get(route('api.v1.order-evidence.show', $media))->assertOk();
});

it('rejects a second active complaint', function (): void {
    [, , , $customer, $order] = orderOperationsContext();
    $order->complaints()->create(['category' => 'other', 'description' => 'Belum selesai', 'status' => 'new']);
    Sanctum::actingAs($customer);

    $this->postJson(orderOperationsRoute($order, 'complaints'), ['category' => 'late', 'description' => 'Terlambat'])
        ->assertUnprocessable()->assertJsonValidationErrors('complaint_id');
    expect($order->complaints()->count())->toBe(1);
});

it('records resolution notes without reopening or changing billing', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $complaint = $order->complaints()->create(['category' => 'damaged', 'description' => 'Noda', 'status' => 'handling']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'complaints'), ['complaint_id' => $complaint->id, 'status' => 'resolved', 'note' => 'Cuci ulang sebagai solusi'])
        ->assertOk()->assertJsonPath('data.complaints.0.resolution_note', 'Cuci ulang sebagai solusi');
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'received', 'total_rupiah' => 12000]);
});

it('counts settlements and manual cashflow once and includes completed unpaid orders', function (): void {
    [, $branch, $admin, , $order] = orderOperationsContext(OrderStatus::Completed);
    $this->freezeTime();
    $payment = Payment::factory()->for($order)->create(['organization_id' => $order->organization_id, 'branch_id' => $branch->id, 'status' => PaymentStatus::Paid, 'method' => PaymentMethod::Cash, 'amount_rupiah' => 5000, 'settled_at' => now()]);
    CashflowEntry::factory()->create(['organization_id' => $order->organization_id, 'branch_id' => $branch->id, 'payment_id' => $payment->id, 'type' => CashflowType::Debit, 'amount_rupiah' => 5000, 'entry_date' => now()]);
    CashflowEntry::factory()->create(['organization_id' => $order->organization_id, 'branch_id' => $branch->id, 'type' => CashflowType::Credit, 'amount_rupiah' => 1000, 'entry_date' => now()]);
    Sanctum::actingAs($admin);

    $this->getJson(route('api.v1.mobile.daily-summary.show', ['branch_id' => $branch->id]))->assertOk()
        ->assertJsonPath('data.receipts.cash', 5000)->assertJsonPath('data.net_income_rupiah', 4000)->assertJsonPath('data.outstanding_rupiah', 7000);
});

it('forbids customer changes to staff-only operations', function (string $resource, array $payload): void {
    [, , , $customer, $order] = orderOperationsContext();
    Sanctum::actingAs($customer);

    $this->postJson(orderOperationsRoute($order, $resource), $payload)->assertForbidden();
    expect($order->tasks()->count())->toBe(0);
    expect($order->estimated_ready_at)->toBeNull();
})->with([
    'estimate' => ['estimate', ['estimated_ready_at' => '2026-10-02T10:00:00+07:00', 'reason' => 'Override pelanggan']],
    'task' => ['tasks', ['kind' => 'delivery', 'status' => 'waiting']],
]);

it('forbids another customer from opening a complaint or its evidence', function (): void {
    Storage::fake('local');
    [, , , , $order] = orderOperationsContext();
    $complaint = $order->complaints()->create(['category' => 'other', 'description' => 'Foto pribadi', 'status' => 'new']);
    $media = $complaint->addMedia(UploadedFile::fake()->image('private.jpg'))->toMediaCollection('evidence', 'local');
    $stranger = User::factory()->create();
    $stranger->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($stranger);

    $this->postJson(orderOperationsRoute($order, 'complaints'), ['category' => 'other', 'description' => 'Bukan milik saya'])->assertForbidden();
    $this->get(route('api.v1.order-evidence.show', $media))->assertForbidden();
    expect($order->complaints()->count())->toBe(1);
});

it('forbids operations outside a branch admin assignment', function (): void {
    [$organization, , $admin, , $order] = orderOperationsContext();
    $otherBranch = Branch::factory()->for($organization)->create();
    $otherOrder = Order::factory()->for($organization)->for($otherBranch)->create();
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($otherOrder, 'tasks'), ['kind' => 'delivery', 'status' => 'waiting'])->assertForbidden();
    $this->postJson(orderOperationsRoute($otherOrder, 'complaints'), ['category' => 'late', 'description' => 'Cabang lain'])->assertForbidden();
    expect($otherOrder->tasks()->count())->toBe(0);
    expect($otherOrder->complaints()->count())->toBe(0);
});

it('does not accept a task id belonging to a different order', function (): void {
    [$organization, $branch, $admin, , $order] = orderOperationsContext();
    $otherOrder = Order::factory()->for($organization)->for($branch)->create();
    $task = $otherOrder->tasks()->create(['kind' => 'delivery', 'status' => 'waiting']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'tasks'), ['task_id' => $task->id, 'kind' => 'delivery', 'status' => 'cancelled'])->assertNotFound();
    $this->assertDatabaseHas('order_tasks', ['id' => $task->id, 'status' => 'waiting']);
});

it('does not accept a complaint id belonging to a different order', function (): void {
    [$organization, $branch, $admin, , $order] = orderOperationsContext();
    $otherOrder = Order::factory()->for($organization)->for($branch)->create();
    $complaint = $otherOrder->complaints()->create(['category' => 'late', 'description' => 'Pesanan lain', 'status' => 'new']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'complaints'), ['complaint_id' => $complaint->id, 'status' => 'handling', 'note' => 'Diproses'])->assertNotFound();
    $this->assertDatabaseHas('order_complaints', ['id' => $complaint->id, 'status' => 'new']);
});

it('rejects skipped and reopened complaint states', function (string $current, string $next): void {
    [, , $admin, , $order] = orderOperationsContext();
    $complaint = $order->complaints()->create(['category' => 'late', 'description' => 'Terlambat', 'status' => $current]);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'complaints'), ['complaint_id' => $complaint->id, 'status' => $next, 'note' => 'Catatan'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('order_complaints', ['id' => $complaint->id, 'status' => $current]);
    expect($complaint->events()->count())->toBe(0);
})->with(['skip handling' => ['new', 'resolved'], 'reopen' => ['resolved', 'handling']]);

it('requires a solution before resolving a complaint', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $complaint = $order->complaints()->create(['category' => 'late', 'description' => 'Terlambat', 'status' => 'handling']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'complaints'), ['complaint_id' => $complaint->id, 'status' => 'resolved'])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->assertDatabaseHas('order_complaints', ['id' => $complaint->id, 'status' => 'handling']);
});

it('reschedules a failed task without creating a second task', function (): void {
    [, , $admin, , $order] = orderOperationsContext();
    $task = $order->tasks()->create(['kind' => 'delivery', 'status' => 'failed']);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'tasks'), ['task_id' => $task->id, 'kind' => 'delivery', 'status' => 'scheduled',
        'operator_name' => 'Budi', 'scheduled_at' => '2026-10-01T10:00:00+07:00', 'address' => 'Jalan Mawar'])->assertOk()->assertJsonPath('data.tasks.0.status', 'scheduled');
    expect($order->tasks()->count())->toBe(1);
    $this->assertDatabaseHas('order_operation_events', ['order_task_id' => $task->id, 'status' => 'scheduled']);
});

it('does not duplicate history on a retried delivery completion', function (): void {
    [, , $admin, , $order] = orderOperationsContext(OrderStatus::Completed);
    $task = $order->tasks()->create(['kind' => 'delivery', 'status' => 'completed', 'recipient_name' => 'Sari', 'completed_at' => now()]);
    Sanctum::actingAs($admin);

    $this->postJson(orderOperationsRoute($order, 'tasks'), ['task_id' => $task->id, 'kind' => 'delivery', 'status' => 'completed', 'recipient_name' => 'Sari'])->assertOk();
    expect($task->events()->count())->toBe(0);
    expect($order->statusEvents()->count())->toBe(0);
});

it('rejects unsupported evidence files without creating a complaint', function (): void {
    Storage::fake('local');
    [, , , $customer, $order] = orderOperationsContext();
    Sanctum::actingAs($customer);

    $this->post(orderOperationsRoute($order, 'complaints'), ['category' => 'other', 'description' => 'Bukti invalid',
        'photos' => [UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    expect($order->complaints()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('limits accumulated evidence to three photos without changing task status', function (): void {
    Storage::fake('local');
    [, , $admin, , $order] = orderOperationsContext();
    $task = $order->tasks()->create(['kind' => 'delivery', 'status' => 'waiting']);
    foreach (range(1, 3) as $index) {
        $task->addMedia(UploadedFile::fake()->image('bukti'.$index.'.jpg'))->toMediaCollection('evidence', 'local');
    }
    Sanctum::actingAs($admin);

    $this->post(orderOperationsRoute($order, 'tasks'), ['task_id' => $task->id, 'kind' => 'delivery', 'status' => 'cancelled',
        'photos' => [UploadedFile::fake()->image('tambahan.jpg')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('photos');
    $this->assertDatabaseHas('order_tasks', ['id' => $task->id, 'status' => 'waiting']);
    expect($task->media()->count())->toBe(3);
    expect($task->events()->count())->toBe(0);
});

it('removes private files when the enclosing transaction rolls back', function (): void {
    Storage::fake('local');
    [, , , , $order] = orderOperationsContext();
    $task = $order->tasks()->create(['kind' => 'pickup', 'status' => 'waiting']);

    expect(fn () => DB::transaction(function () use ($task): void {
        app(StoreOrderEvidence::class)->handle($task, [UploadedFile::fake()->image('rollback.jpg')]);
        throw new RuntimeException('Rollback after upload');
    }))->toThrow(RuntimeException::class, 'Rollback after upload');

    expect($task->media()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('returns full scoped statistics while paginating customer multiword search', function (): void {
    [$organization, $branch, , $customer, $order] = orderOperationsContext();
    $branch->update(['name' => 'Cabang Kemang']);
    $order->items()->create(['service_name_snapshot' => 'Cuci Kering', 'service_code_snapshot' => 'CKL', 'price_option_name_snapshot' => 'Reguler',
        'price_type_snapshot' => 'per_kilogram', 'requested_weight_grams' => 1000, 'chargeable_weight_grams' => 1000, 'calculated_base_price_rupiah' => 12000, 'excess_weight_grams' => 0, 'excess_price_rupiah' => 0, 'subtotal_rupiah' => 12000]);
    Order::factory()->count(30)->for($organization)->for($branch)->create(['customer_id' => $customer->id]);
    Order::factory()->for($organization)->for($branch)->create();
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.orders.index', ['paginated' => 1, 'search' => 'Kemang CKL']))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $order->id)->assertJsonPath('statistics.total', 31)->assertJsonPath('meta.total', 1);
});

it('uses settlement date and current paid status for each receipt method', function (): void {
    [, $branch, $admin, , $order] = orderOperationsContext();
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    foreach ([PaymentMethod::Cash->value => 2000, PaymentMethod::Qris->value => 3000, PaymentMethod::Gateway->value => 4000] as $method => $amount) {
        Payment::factory()->for($order)->create(['organization_id' => $order->organization_id, 'branch_id' => $branch->id, 'status' => PaymentStatus::Paid,
            'method' => $method, 'amount_rupiah' => $amount, 'created_at' => now()->subDays(2), 'settled_at' => now()->subDay()]);
    }
    Payment::factory()->for($order)->create(['organization_id' => $order->organization_id, 'branch_id' => $branch->id, 'status' => PaymentStatus::Refunded, 'amount_rupiah' => 9000, 'settled_at' => now()->subDay()]);
    Payment::factory()->for($order)->create(['organization_id' => $order->organization_id, 'branch_id' => $branch->id, 'status' => PaymentStatus::Paid, 'amount_rupiah' => 7000, 'settled_at' => now()]);
    Payment::factory()->create(['status' => PaymentStatus::Paid, 'amount_rupiah' => 50000, 'settled_at' => now()->subDay()]);
    Sanctum::actingAs($admin);

    $this->getJson(route('api.v1.mobile.daily-summary.show', ['date' => '2026-09-29']))->assertOk()
        ->assertJsonPath('data.receipts.cash', 2000)->assertJsonPath('data.receipts.qris', 3000)->assertJsonPath('data.receipts.gateway', 4000)
        ->assertJsonPath('data.net_income_rupiah', 9000);
});
