<?php

use App\Enums\CashflowType;
use App\Enums\OrderStatus;
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

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows owner to download order import template csv', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $response = $this->actingAs($owner)->get(route('cms.orders.template'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('template-import-pesanan.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('nama_pelanggan,no_hp,organisasi,cabang,layanan');
});

it('allows owner to export orders as csv', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Utama']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'ORD-EXP-001',
        'customer_name_snapshot' => 'Budi Santoso',
        'customer_whatsapp_phone_snapshot' => '081234567890',
        'total_rupiah' => 50000,
        'status' => OrderStatus::Completed,
    ]);

    $response = $this->actingAs($owner)->get(route('cms.orders.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $content = $response->streamedContent();
    expect($content)->toContain('ORD-EXP-001')
        ->toContain('Budi Santoso')
        ->toContain('081234567890');
});

it('imports orders from csv, creates customer with pin 676767, completes order, and records payment and cashflow', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Bersih']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $csvRows = [
        'nama_pelanggan,no_hp,organisasi,cabang,layanan,opsi_harga,berat_kg,total_biaya,status_pembayaran,metode_pembayaran,status_pesanan,tanggal,catatan',
        'Joko Susanto,089912345678,Laundry Bersih,Cabang Baru,Cuci Komplit,Reguler 2 Hari,4.0,40000,lunas,cash,completed,2026-03-15 10:00:00,Pakaian kerja',
    ];
    $csvContent = implode("\n", $csvRows);
    $file = UploadedFile::fake()->createWithContent('orders.csv', $csvContent);

    $response = $this->actingAs($owner)->post(route('cms.orders.import'), [
        'file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // 1. Verify customer created with 6-digit PIN 676767
    /** @var User|null $customer */
    $customer = User::query()->where('whatsapp_phone', '6289912345678')->first();
    expect($customer)->not->toBeNull();
    expect($customer->name)->toBe('Joko Susanto');
    expect($customer->hasRole(RoleName::Customer->value))->toBeTrue();
    expect($customer->customerPinMatches('676767'))->toBeTrue();

    // 2. Verify branch auto-created
    $branch = Branch::query()->where('organization_id', $organization->id)->where('name', 'Cabang Baru')->first();
    expect($branch)->not->toBeNull();

    // 3. Verify order created with completed status
    /** @var Order|null $order */
    $order = Order::query()->where('organization_id', $organization->id)->latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe(OrderStatus::Completed);
    expect($order->total_rupiah)->toBe(40000);

    // 4. Verify payment recorded as paid
    $payment = Payment::query()->where('order_id', $order->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe(PaymentStatus::Paid);
    expect($payment->amount_rupiah)->toBe(40000);

    // 5. Verify Cashflow entry recorded as debit
    $cashflow = CashflowEntry::query()->where('payment_id', $payment->id)->first();
    expect($cashflow)->not->toBeNull();
    expect($cashflow->type)->toBe(CashflowType::Debit);
    expect($cashflow->amount_rupiah)->toBe(40000);
});

it('supports hyphenated phone numbers and normalizes them to Indonesian format', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Cepat']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $csvRows = [
        'nama_pelanggan,no_hp,organisasi,cabang,layanan,opsi_harga,berat_kg,total_biaya,status_pembayaran,metode_pembayaran,status_pesanan,tanggal,catatan',
        'Rina Wati,0812-8921-3041,Laundry Cepat,Cabang Dago,Cuci Kering,Reguler,3.0,30000,lunas,cash,completed,2026-03-15 10:00:00,Lipat rapi',
    ];
    $file = UploadedFile::fake()->createWithContent('orders.csv', implode("\n", $csvRows));

    $response = $this->actingAs($owner)->post(route('cms.orders.import'), ['file' => $file]);
    $response->assertRedirect();

    $customer = User::query()->where('name', 'Rina Wati')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->whatsapp_phone)->toBe('6281289213041');

    $order = Order::query()->where('organization_id', $organization->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->customer_whatsapp_phone_snapshot)->toBe('6281289213041');
});

it('treats dash in phone column as empty data without error', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Kilat']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $csvRows = [
        'nama_pelanggan,no_hp,organisasi,cabang,layanan,opsi_harga,berat_kg,total_biaya,status_pembayaran,metode_pembayaran,status_pesanan,tanggal,catatan',
        'Pelanggan Anonim,-,Laundry Kilat,Cabang Pasteur,Cuci Satuan,Kemeja,1.0,15000,lunas,cash,completed,2026-03-15 10:00:00,Tanpa nomor HP',
    ];
    $file = UploadedFile::fake()->createWithContent('orders.csv', implode("\n", $csvRows));

    $response = $this->actingAs($owner)->post(route('cms.orders.import'), ['file' => $file]);
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $customer = User::query()->where('name', 'Pelanggan Anonim')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->whatsapp_phone)->toBeNull();

    $order = Order::query()->where('organization_id', $organization->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->customer_whatsapp_phone_snapshot)->toBeNull()
        ->and($order->total_rupiah)->toBe(15000);
});

it('returns json with import_id when requested and provides status endpoint', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Express']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $csvRows = [
        'nama_pelanggan,no_hp,organisasi,cabang,layanan,opsi_harga,berat_kg,total_biaya,status_pembayaran,metode_pembayaran,status_pesanan,tanggal,catatan',
        'Doni,081234567899,Laundry Express,Cabang Tebet,Cuci Kering,Reguler,2.0,20000,lunas,cash,completed,2026-03-15 10:00:00,',
    ];
    $file = UploadedFile::fake()->createWithContent('orders.csv', implode("\n", $csvRows));

    $response = $this->actingAs($owner)->postJson(route('cms.orders.import'), ['file' => $file]);
    $response->assertOk()
        ->assertJsonStructure(['import_id', 'status', 'total_rows']);

    $importId = (string) $response->json('import_id');

    $statusResponse = $this->actingAs($owner)->getJson(route('cms.orders.import.status', $importId));
    $statusResponse->assertOk()
        ->assertJsonStructure(['id', 'status', 'total_rows', 'processed_rows', 'imported']);
});
