<?php

use App\Actions\Payments\SelectPublicOrderPayment;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentCannotBeCreated;
use App\Jobs\SendOrderPushNotification;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

const SAFETY_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';
const SAFETY_GATEWAY_URL = 'https://api.paywuz.test/v1';

beforeEach(function (): void {
    $this->withoutVite();
    Http::preventStrayRequests();
    config()->set([
        'payment-gateways.drivers.paywuz.enabled' => true,
        'payment-gateways.drivers.paywuz.api_key' => 'safety-test-secret',
        'payment-gateways.drivers.paywuz.base_url' => SAFETY_GATEWAY_URL,
        'payment-gateways.drivers.paywuz.method_cache_seconds' => 0,
    ]);
});

function safetyOrder(): Order
{
    $branch = Branch::factory()->create([
        'qris_enabled' => true,
        'qris_static_payload' => SAFETY_QRIS,
    ]);

    return Order::factory()->for($branch)->create([
        'organization_id' => $branch->organization_id,
        'total_rupiah' => 50000,
        'priced_at' => now(),
    ]);
}

function safetyPayment(Order $order, PaymentMethod $method = PaymentMethod::Qris): Payment
{
    return Payment::factory()->for($order)->create([
        'organization_id' => $order->organization_id,
        'branch_id' => $order->branch_id,
        'method' => $method,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 50000,
        'settled_at' => null,
        'qris_payload' => $method === PaymentMethod::Qris ? SAFETY_QRIS : null,
        'gateway_driver' => $method === PaymentMethod::Gateway ? PaymentGatewayDriver::Paywuz : null,
        'gateway_method' => $method === PaymentMethod::Gateway ? 'BNIVA' : null,
        'gateway_payment_id' => $method === PaymentMethod::Gateway ? 'safety-provider-payment' : null,
        'gateway_order_id' => $method === PaymentMethod::Gateway ? 'safety-provider-order' : null,
        'gateway_status' => $method === PaymentMethod::Gateway ? 'pending' : null,
    ]);
}

it('keeps unpriced zero-total orders distinct from confirmed free orders', function (bool $priced): void {
    $order = safetyOrder();
    $order->update(['total_rupiah' => 0, 'priced_at' => $priced ? now() : null]);

    $response = $this->get(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))->assertOk();

    if ($priced) {
        $response->assertSee('Struk Pembayaran Lunas');
    } else {
        $response->assertDontSee('Struk Pembayaran Lunas')->assertSee('Penentuan harga sedang diproses');
    }

    Http::assertNothingSent();
    expect($order->payments()->count())->toBe(0);
})->with([true, false]);

it('keeps tracking accessible during gateway outages', function (bool $paid, bool $timeout): void {
    $order = safetyOrder();
    $payment = safetyPayment($order);
    if ($paid) {
        $payment->update(['status' => PaymentStatus::Paid]);
    }
    Http::fake([SAFETY_GATEWAY_URL.'/payment-methods' => $timeout ? Http::failedConnection() : Http::response([], 503)]);

    $response = $this->get(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))->assertOk();

    if ($paid) {
        $response->assertSee('Struk Pembayaran Lunas');
        Http::assertNothingSent();
    } else {
        $response->assertSee('Unduh QRIS')->assertSee('Gateway sementara tidak tersedia');
        Http::assertSentCount(1);
    }
})->with([true, false])->with([true, false]);

it('rolls back a QRIS replacement when the provider fails', function (string $providerStatus): void {
    $order = safetyOrder();
    $payment = safetyPayment($order);
    Http::fake([
        SAFETY_GATEWAY_URL.'/payment-methods' => Http::response(['data' => [[
            'code' => 'BNIVA', 'name' => 'BNI Virtual Account', 'type' => 'virtual_account',
            'fee' => ['totalIdr' => 4000], 'limits' => ['minIdr' => 10000, 'maxIdr' => 10000000],
        ]]]),
        SAFETY_GATEWAY_URL.'/transactions' => function (Request $request) use ($providerStatus) {
            return $providerStatus === 'unavailable' ? Http::response([], 503) : Http::response(['data' => [
                'id' => 'failed-transaction', 'orderId' => $request['orderId'],
                'amount' => $request['amount'], 'status' => $providerStatus, 'paymentMethod' => 'BNIVA',
                'totalPayment' => 54000, 'paymentNumber' => '880812345678',
            ]], 201);
        },
    ]);

    $this->post(URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]), [
        'method' => 'gateway', 'gateway_driver' => 'paywuz', 'gateway_method' => 'BNIVA',
    ])->assertRedirect(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))
        ->assertSessionHas('error');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->statusEvents()->count())->toBe(0)
        ->and($order->payments()->count())->toBe(1);
    Http::assertSentCount(2);
})->with(['unavailable', 'failed', 'cancelled']);

it('locks active gateway payments without losing a subsequent paid webhook', function (string $method): void {
    $order = safetyOrder();
    $payment = safetyPayment($order, PaymentMethod::Gateway);
    Queue::fake([SendOrderPushNotification::class]);
    $this->post(URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]), [
        'method' => $method, 'gateway_driver' => 'paywuz', 'gateway_method' => 'BRIVA',
    ])->assertRedirect()->assertSessionHas('error');
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->payments()->count())->toBe(1);

    $rawBody = json_encode([
        'event' => 'transaction.paid',
        'data' => [
            'id' => $payment->gateway_payment_id, 'orderId' => $payment->gateway_order_id,
            'amount' => 50000, 'paymentMethod' => 'BNIVA', 'status' => 'success', 'paidAt' => now()->toISOString(),
        ],
        'timestamp' => now()->toISOString(),
    ], JSON_THROW_ON_ERROR);
    $headers = [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_PAYWUZ_SIGNATURE' => 'sha256='.hash_hmac('sha256', $rawBody, 'safety-test-secret'),
        'HTTP_X_PAYWUZ_EVENT' => 'transaction.paid', 'HTTP_X_PAYWUZ_DELIVERY' => 'safety-delivery',
    ];
    $this->call('POST', route('api.v1.payment-gateways.paywuz.webhook'), [], [], [], $headers, $rawBody)
        ->assertOk()->assertJsonPath('status', PaymentStatus::Paid->value);
    $this->call('POST', route('api.v1.payment-gateways.paywuz.webhook'), [], [], [], $headers, $rawBody)
        ->assertOk()->assertJsonPath('duplicate', true);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->statusEvents()->count())->toBe(1);
    Queue::assertPushed(SendOrderPushNotification::class, 1);
    Http::assertNothingSent();
})->with(['qris', 'gateway']);

it('reuses an identical pending gateway without provider requests', function (): void {
    $order = safetyOrder();
    $payment = safetyPayment($order, PaymentMethod::Gateway);
    $this->post(URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]), [
        'method' => 'gateway', 'gateway_driver' => 'paywuz', 'gateway_method' => 'bniva',
    ])->assertRedirect()->assertSessionMissing('error');
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->payments()->count())->toBe(1);
    Http::assertNothingSent();
});

it('offers QRIS again after a confirmed gateway cancellation', function (): void {
    $order = safetyOrder();
    safetyPayment($order, PaymentMethod::Gateway)->update(['status' => PaymentStatus::Cancelled, 'gateway_status' => 'cancelled']);
    config()->set('payment-gateways.drivers.paywuz.enabled', false);
    $this->get(URL::signedRoute('orders.public.show', ['order' => $order->order_number]))
        ->assertOk()->assertSee('value="qris"', false)
        ->assertSee('Pilih Metode Pembayaran')
        ->assertDontSee('QRIS Dinamis Aktif');
    $this->post(URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]), ['method' => 'qris'])
        ->assertRedirect()->assertSessionMissing('error');
    expect($order->payments()->where('status', PaymentStatus::Pending)->count())->toBe(1);
    Http::assertNothingSent();
});

it('reloads pricing and paid amounts before selecting a payment and reuses repeated selections', function (): void {
    $order = safetyOrder();
    Order::query()->whereKey($order->id)->update(['total_rupiah' => 70000]);
    safetyPayment($order)->update(['status' => PaymentStatus::Paid, 'amount_rupiah' => 20000]);

    $action = app(SelectPublicOrderPayment::class);
    $payment = $action->handle($order, PaymentMethod::Qris);
    $repeatedPayment = $action->handle($order, PaymentMethod::Qris);

    expect($payment->amount_rupiah)->toBe(50000);
    expect($repeatedPayment->id)->toBe($payment->id);
    expect($order->payments()->where('status', PaymentStatus::Pending)->count())->toBe(1);
    Http::assertNothingSent();
});

it('rejects a stale order that has since become fully paid', function (): void {
    $order = safetyOrder();
    $order->load('payments');
    safetyPayment($order)->update(['status' => PaymentStatus::Paid]);

    expect(fn () => app(SelectPublicOrderPayment::class)->handle($order, PaymentMethod::Qris))
        ->toThrow(PaymentCannotBeCreated::class);
    expect($order->payments()->count())->toBe(1);
    Http::assertNothingSent();
});

it('rejects unsigned public payment selections without creating payments', function (): void {
    $order = safetyOrder();
    $this->post(route('orders.public.payment.store', ['order' => $order->order_number]), ['method' => 'qris'])
        ->assertForbidden();
    expect($order->payments()->count())->toBe(0);
    Http::assertNothingSent();
});

it('refuses public cash payments and unpriced orders', function (string $method, bool $priced): void {
    $order = safetyOrder();
    $order->update(['priced_at' => $priced ? now() : null]);
    $this->post(URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]), ['method' => $method])
        ->assertRedirect()->assertSessionHas('error');
    expect($order->payments()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['cash' => ['cash', true], 'unpriced' => ['qris', false]]);

it('requires a gateway method before creating a payment', function (): void {
    $order = safetyOrder();
    $this->post(URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]), ['method' => 'gateway'])
        ->assertSessionHasErrors('gateway_method');
    expect($order->payments()->count())->toBe(0);
    Http::assertNothingSent();
});

it('redirects legacy signed receipts to the signed tracking page', function (): void {
    $order = safetyOrder();
    $legacyUrl = URL::temporarySignedRoute('receipts.public.show', now()->addDay(), ['order' => $order->id]);
    $this->get($legacyUrl)->assertRedirect(URL::signedRoute('orders.public.show', ['order' => $order->order_number]));
    Http::assertNothingSent();
});

it('rejects unsigned tampered and expired legacy receipt links', function (): void {
    $order = safetyOrder();
    $this->get(route('receipts.public.show', ['order' => $order->id]))->assertForbidden();
    $signedUrl = URL::signedRoute('receipts.public.show', ['order' => $order->id]);
    $this->get($signedUrl.'&tampered=1')->assertForbidden();
    $expiredUrl = URL::temporarySignedRoute('receipts.public.show', now()->subMinute(), ['order' => $order->id]);
    $this->get($expiredUrl)->assertForbidden();
    Http::assertNothingSent();
});
