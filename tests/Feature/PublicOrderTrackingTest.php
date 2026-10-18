<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

const PUBLIC_TRACKING_TEST_STATIC_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';
const PUBLIC_TRACKING_PAYWUZ_BASE_URL = 'https://api.paywuz.test/v1';

beforeEach(function (): void {
    $this->withoutVite();
    Cache::forget('payment-gateways.paywuz.methods');
    Http::fake([
        'https://qris.karuhundeveloper.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'token' => 'qris_token_test_123',
                'amount' => 50000,
                'unique_code' => 0,
                'qris_string' => '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC',
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ], 200),
    ]);
    config()->set('payment-gateways.drivers.paywuz.base_url', PUBLIC_TRACKING_PAYWUZ_BASE_URL);
    config()->set('payment-gateways.drivers.paywuz.method_cache_seconds', 0);
});

it('renders signed order tracking publicly using the human order number', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Dago']);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260915-00000042',
        'status' => OrderStatus::Washing,
    ]);
    $order->statusEvents()->createMany([
        [
            'actor_name_snapshot' => 'Admin Cabang',
            'status' => OrderStatus::Received,
        ],
        [
            'actor_name_snapshot' => 'Admin Cabang',
            'status' => OrderStatus::Washing,
            'note' => 'Sedang diproses dengan hati-hati',
        ],
    ]);
    $url = URL::signedRoute('orders.public.show', [
        'order' => $order->order_number,
    ]);

    expect($url)->toContain('/pesanan/LDR-20260915-00000042?')
        ->not->toContain('/pesanan/'.$order->id.'?')
        ->not->toContain('expires=');

    $this->get($url)
        ->assertOk()
        ->assertSee('Detail &amp; Struk Pesanan', false)
        ->assertSee('LDR-20260915-00000042')
        ->assertSee('Cabang Dago')
        ->assertSee('Dicuci')
        ->assertSee('Sedang diproses dengan hati-hati')
        ->assertSee('property="og:title" content="Detail &amp; Struk LDR-20260915-00000042 | LaundryHub"', false)
        ->assertSee('property="og:url" content="'.e($url).'"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
});

it('rejects unsigned and tampered public order tracking URLs', function (): void {
    $order = Order::factory()->create(['order_number' => 'LDR-20260915-00000043']);

    $this->get(route('orders.public.show', ['order' => $order->order_number]))
        ->assertForbidden();

    $validUrl = URL::signedRoute('orders.public.show', [
        'order' => $order->order_number,
    ]);

    $this->get($validUrl.'&tampered=1')->assertForbidden();
});

it('displays order preview with items, subtotal, delivery fee, and payment summary', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'name' => 'Cabang Pasteur',
        'qris_enabled' => false,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260916-00000010',
        'customer_name_snapshot' => 'Siti Nurhaliza',
        'items_subtotal_rupiah' => 45000,
        'delivery_fee_rupiah' => 10000,
        'total_rupiah' => 55000,
        'priced_at' => now(),
    ]);

    OrderItem::factory()->for($order)->create([
        'service_name_snapshot' => 'Cuci Kering Lipat',
        'requested_weight_grams' => 3000,
        'subtotal_rupiah' => 45000,
    ]);

    $url = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Rincian pesanan')
        ->assertSee('Siti Nurhaliza')
        ->assertSee('Cuci Kering Lipat')
        ->assertSee('3,00 kg')
        ->assertSee('Rp45.000')
        ->assertSee('Rp10.000')
        ->assertSee('Rp55.000')
        ->assertSee('Belum Lunas');
});

it('automatically generates and displays dynamic QRIS for unpaid order when branch has QRIS enabled', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'name' => 'Cabang Merdeka',
        'qris_enabled' => true,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260916-00000011',
        'customer_name_snapshot' => 'Budi Santoso',
        'items_subtotal_rupiah' => 50000,
        'total_rupiah' => 50000,
        'priced_at' => now(),
    ]);

    $url = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

    $response = $this->get($url);
    $response->assertOk()
        ->assertSee('QRIS Dinamis')
        ->assertSee('Rp50.000')
        ->assertSee('Unduh QRIS')
        ->assertSee('Cek Status Sekarang')
        ->assertSee('data:image/svg+xml;base64,');

    expect($order->payments()->where('status', PaymentStatus::Pending)->exists())->toBeTrue();
    $payment = $order->payments()->where('status', PaymentStatus::Pending)->first();
    expect($payment->method)->toBe(PaymentMethod::Qris)
        ->and($payment->amount_rupiah)->toBe(50000)
        ->and($payment->qris_payload)->not->toBeNull();
});

it('displays existing pending QRIS payment created by branch admin', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260916-00000012',
        'total_rupiah' => 75000,
        'priced_at' => now(),
    ]);

    $existingPayment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'payment_number' => 'PAY-20260916-99999999',
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 75000,
        'qris_payload' => PUBLIC_TRACKING_TEST_STATIC_QRIS,
    ]);

    $url = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

    $this->get($url)
        ->assertOk()
        ->assertSee('PAY-20260916-99999999')
        ->assertSee('Rp75.000')
        ->assertSee('Unduh QRIS');
});

it('allows customer to switch payment method to payment gateway via signed POST', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260916-00000013',
        'total_rupiah' => 60000,
        'priced_at' => now(),
    ]);

    // Create an initial pending QRIS payment
    $qrisPayment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 60000,
        'qris_payload' => PUBLIC_TRACKING_TEST_STATIC_QRIS,
    ]);

    config()->set('payment-gateways.drivers.paywuz.enabled', true);
    config()->set('payment-gateways.drivers.paywuz.api_key', 'paywuz-test-secret');

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response([
                'data' => [
                    [
                        'code' => 'BNIVA',
                        'name' => 'BNI Virtual Account',
                        'type' => 'virtual_account',
                        'fee' => ['totalIdr' => 4000],
                        'limits' => ['minIdr' => 10000, 'maxIdr' => 10000000],
                    ],
                ],
            ]);
        }

        $payload = $request->data();

        return Http::response([
            'status' => 'success',
            'data' => [
                'id' => 'tx-paywuz-12345',
                'orderId' => $payload['orderId'],
                'amount' => $payload['amount'],
                'totalPayment' => 64000,
                'status' => 'PENDING',
                'paymentMethod' => 'BNIVA',
                'paymentNumber' => '880812345678',
                'paymentUrl' => 'https://pay.paywuz.test/tx-paywuz-12345',
                'expiresAt' => now()->addHour()->toISOString(),
            ],
        ], 201);
    });

    $postUrl = URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]);

    $response = $this->post($postUrl, [
        'method' => 'gateway',
        'gateway_driver' => 'paywuz',
        'gateway_method' => 'BNIVA',
    ]);

    $trackingUrl = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);
    $response->assertRedirect($trackingUrl);

    // Old QRIS payment must be cancelled
    expect($qrisPayment->fresh()->status)->toBe(PaymentStatus::Cancelled);

    // New gateway payment created
    $gatewayPayment = $order->payments()->where('status', PaymentStatus::Pending)->first();
    expect($gatewayPayment)->not->toBeNull()
        ->and($gatewayPayment->method)->toBe(PaymentMethod::Gateway)
        ->and($gatewayPayment->gateway_method)->toBe('BNIVA')
        ->and($gatewayPayment->gateway_payment_number)->toBe('880812345678')
        ->and($gatewayPayment->gateway_payment_url)->toBe('https://pay.paywuz.test/tx-paywuz-12345');

    // Visiting tracking URL displays the gateway information
    $this->get($trackingUrl)
        ->assertOk()
        ->assertSee('Payment Gateway')
        ->assertSee('880812345678')
        ->assertSee('Buka Halaman Pembayaran')
        ->assertSee('Pembayaran gateway masih aktif')
        ->assertDontSee('Pilih metode pembayaran lain');
});

it('displays lunas badge and digital receipt in the unified page when order is fully paid', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260916-00000014',
        'total_rupiah' => 35000,
        'priced_at' => now(),
    ]);

    Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'payment_number' => 'PAY-20260916-00000014',
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 35000,
        'settled_at' => now(),
    ]);

    $url = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Detail &amp; Struk Pesanan', false)
        ->assertSee('Struk Pembayaran Lunas')
        ->assertSee('PAY-20260916-00000014')
        ->assertSee('Lunas')
        ->assertSee('Cetak / Simpan Struk')
        ->assertDontSee('Unduh QRIS');
});

it('escapes customer branch and address content in the unified public order receipt', function (): void {
    $customerName = '<script>alert("customer")</script>';
    $branchName = '<b>Cabang Uji</b>';
    $branchAddress = '<img src=x onerror=alert(1)>';
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'name' => $branchName,
        'address' => $branchAddress,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_name_snapshot' => $customerName,
    ]);
    $url = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

    $this->get($url)
        ->assertOk()
        ->assertDontSee($customerName, false)
        ->assertDontSee($branchName, false)
        ->assertDontSee($branchAddress, false)
        ->assertSee(e($customerName), false)
        ->assertSee(e($branchName), false)
        ->assertSee(e($branchAddress), false);
});

it('blocks switching an active gateway back to QRIS via signed POST', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260916-00000015',
        'total_rupiah' => 40000,
        'priced_at' => now(),
    ]);

    $gatewayPayment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Gateway,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 40000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz,
        'gateway_method' => 'BNIVA',
        'gateway_payment_number' => '998811223344',
    ]);

    $postUrl = URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]);

    $response = $this->post($postUrl, [
        'method' => 'qris',
    ]);

    $trackingUrl = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);
    $response->assertRedirect($trackingUrl)->assertSessionHas('error');

    expect($gatewayPayment->fresh()->status)->toBe(PaymentStatus::Pending);
    expect($order->payments()->count())->toBe(1);

    $this->get($trackingUrl)
        ->assertOk()
        ->assertSee('Pembayaran gateway masih aktif')
        ->assertDontSee('Pilih metode pembayaran lain')
        ->assertDontSee('Unduh QRIS');
});

it('displays active QRIS container with countdown when QRIS is within 15 minutes window', function (): void {
    $organization = Organization::factory()->create([
        'qris_enabled' => true,
        'qris_api_key' => 'test-key',
    ]);
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20261018-00000099',
        'total_rupiah' => 25000,
        'priced_at' => now(),
    ]);

    Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 25000,
        'qris_payload' => '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC',
        'qris_token' => 'tok_active_qris_utc',
        'qris_expires_at' => Carbon::now('UTC')->addMinutes(15)->toISOString(),
    ]);

    $url = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

    $response = $this->get($url);
    $response->assertOk()
        ->assertViewHas('isQrisExpired', false)
        ->assertViewHas('qrisRemainingSeconds', fn ($val) => $val >= 895 && $val <= 900);
});
