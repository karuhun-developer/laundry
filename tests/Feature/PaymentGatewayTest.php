<?php

use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGatewayDelivery;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

const PAYWUZ_TEST_BASE_URL = 'https://api.paywuz.test/v1';
const PAYWUZ_TEST_API_KEY = 'paywuz-test-secret';
const PAYMENT_GATEWAY_TEST_STATIC_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Cache::forget('payment-gateways.paywuz.methods');
    config()->set('payment-gateways.drivers.paywuz.base_url', PAYWUZ_TEST_BASE_URL);
    config()->set('payment-gateways.drivers.paywuz.method_cache_seconds', 0);
});

/** @return array{organization: Organization, branch: Branch, admin: User, order: Order} */
function paymentGatewayTestContext(int $amountRupiah = 50000): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_static_payload' => PAYMENT_GATEWAY_TEST_STATIC_QRIS,
    ]);
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'items_subtotal_rupiah' => $amountRupiah,
        'total_rupiah' => $amountRupiah,
    ]);

    Sanctum::actingAs($admin);

    return compact('organization', 'branch', 'admin', 'order');
}

/** @return array<string, mixed> */
function paywuzPaymentMethodsPayload(): array
{
    return [
        'data' => [
            [
                'code' => 'VA',
                'name' => 'Pilih bank di halaman pembayaran',
                'type' => 'meta',
                'fee' => ['totalIdr' => 0],
                'limits' => ['minIdr' => 1000, 'maxIdr' => 10000000],
            ],
            [
                'code' => 'QRIS',
                'name' => 'QRIS',
                'type' => 'qris',
                'fee' => ['totalIdr' => 0],
                'limits' => ['minIdr' => 1000, 'maxIdr' => 10000000],
            ],
            [
                'code' => 'BNIVA',
                'name' => 'BNI Virtual Account',
                'type' => 'virtual_account',
                'fee' => ['totalIdr' => 4000],
                'limits' => ['minIdr' => 10000, 'maxIdr' => 10000000],
            ],
            [
                'code' => 'ALL',
                'name' => 'Semua metode',
                'type' => 'meta',
                'fee' => ['totalIdr' => 0],
                'limits' => ['minIdr' => 1000, 'maxIdr' => 10000000],
            ],
        ],
    ];
}

/** @param array{organization: Organization, branch: Branch, order: Order} $context */
function paymentStoreRoute(array $context): string
{
    return route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $context['organization'],
        'branch' => $context['branch'],
        'order' => $context['order'],
    ]);
}

function configurePaywuz(bool $enabled = true, ?string $apiKey = PAYWUZ_TEST_API_KEY): void
{
    config()->set('payment-gateways.drivers.paywuz.enabled', $enabled);
    config()->set('payment-gateways.drivers.paywuz.api_key', $apiKey);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array{event: string, data: array<string, mixed>, timestamp: string}
 */
function paywuzWebhookPayload(Payment $payment, array $overrides = []): array
{
    return [
        'event' => $overrides['event'] ?? 'transaction.paid',
        'data' => [
            'id' => $overrides['id'] ?? $payment->gateway_payment_id,
            'orderId' => $overrides['orderId'] ?? $payment->gateway_order_id,
            'amount' => $overrides['amount'] ?? $payment->amount_rupiah,
            'paymentMethod' => $overrides['paymentMethod'] ?? $payment->gateway_method,
            'status' => $overrides['status'] ?? 'success',
            'paidAt' => $overrides['paidAt'] ?? '2026-09-14T12:00:00+07:00',
        ],
        'timestamp' => '2026-09-14T12:00:01+07:00',
    ];
}

/** @param array<string, mixed> $overrides */
function postPaywuzWebhook(
    Payment $payment,
    string $deliveryId,
    array $overrides = [],
    ?string $signature = null,
    ?string $headerEvent = null,
): TestResponse {
    $payload = paywuzWebhookPayload($payment, $overrides);
    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature ??= 'sha256='.hash_hmac('sha256', $rawBody, PAYWUZ_TEST_API_KEY);

    return test()->call(
        'POST',
        route('api.v1.payment-gateways.paywuz.webhook'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYWUZ_SIGNATURE' => $signature,
            'HTTP_X_PAYWUZ_EVENT' => $headerEvent ?? $payload['event'],
            'HTTP_X_PAYWUZ_DELIVERY' => $deliveryId,
        ],
        $rawBody,
    );
}

/** @param array{organization: Organization, branch: Branch, order: Order} $context */
function gatewayPayment(array $context): Payment
{
    return Payment::factory()->create([
        'organization_id' => $context['organization']->id,
        'branch_id' => $context['branch']->id,
        'order_id' => $context['order']->id,
        'payment_number' => 'PAY-20260914-00000001',
        'method' => PaymentMethod::Gateway,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 50000,
        'settled_at' => null,
        'gateway_driver' => PaymentGatewayDriver::Paywuz,
        'gateway_payment_id' => 'trx-paywuz-1',
        'gateway_order_id' => 'PAY-20260914-00000001',
        'gateway_method' => 'QRIS',
        'gateway_status' => 'pending',
        'gateway_payment_number' => '000201010212PAYWUZ',
        'gateway_payment_url' => null,
        'gateway_expires_at' => '2026-09-14T13:00:00+07:00',
        'gateway_payload' => ['transaction' => ['id' => 'trx-paywuz-1']],
    ]);
}

it('returns no gateway methods without outbound requests when disabled or incomplete', function (
    bool $enabled,
    ?string $apiKey,
): void {
    configurePaywuz($enabled, $apiKey);
    Http::preventStrayRequests();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.payment-gateway-methods.index'))
        ->assertOk()
        ->assertExactJson(['data' => []]);

    Http::assertNothingSent();
})->with([
    'disabled' => [false, PAYWUZ_TEST_API_KEY],
    'enabled without API key' => [true, null],
]);

it('keeps cash and branch QRIS working while rejecting gateway payments when disabled', function (): void {
    configurePaywuz(false, null);
    Http::preventStrayRequests();
    $context = paymentGatewayTestContext();

    $this->postJson(paymentStoreRoute($context), [
        'method' => PaymentMethod::Cash->value,
        'amount_rupiah' => 50000,
    ])->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.gateway', null);

    $qrisOrder = Order::factory()
        ->for($context['organization'])
        ->for($context['branch'])
        ->create(['items_subtotal_rupiah' => 50000, 'total_rupiah' => 50000]);
    $qrisContext = [...$context, 'order' => $qrisOrder];

    $this->postJson(paymentStoreRoute($qrisContext), [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 50000,
    ])->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Pending->value)
        ->assertJsonPath('data.gateway', null);

    $gatewayOrder = Order::factory()
        ->for($context['organization'])
        ->for($context['branch'])
        ->create(['items_subtotal_rupiah' => 50000, 'total_rupiah' => 50000]);
    $gatewayContext = [...$context, 'order' => $gatewayOrder];

    $this->postJson(paymentStoreRoute($gatewayContext), [
        'method' => PaymentMethod::Gateway->value,
        'amount_rupiah' => 50000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => 'QRIS',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_UNAVAILABLE');

    expect(Payment::query()->where('order_id', $gatewayOrder->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('lists direct Paywuz methods and excludes provider meta methods', function (): void {
    configurePaywuz();
    Http::fake([
        PAYWUZ_TEST_BASE_URL.'/payment-methods' => Http::response(paywuzPaymentMethodsPayload()),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.payment-gateway-methods.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'QRIS')
        ->assertJsonPath('data.0.is_default', true)
        ->assertJsonPath('data.1.code', 'BNIVA');

    Http::assertSent(fn (Request $request): bool => $request->url() === PAYWUZ_TEST_BASE_URL.'/payment-methods'
        && $request->hasHeader('Authorization', 'Bearer '.PAYWUZ_TEST_API_KEY));
});

it('creates and persists a Paywuz QRIS payment with the expected provider request', function (): void {
    configurePaywuz();
    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response(paywuzPaymentMethodsPayload());
        }

        $payload = $request->data();

        return Http::response([
            'data' => [
                'id' => 'trx-qris-1',
                'orderId' => $payload['orderId'],
                'amount' => 50000,
                'totalPayment' => 50000,
                'paymentMethod' => 'QRIS',
                'paymentNumber' => '000201010212PAYWUZQRIS',
                'paymentUrl' => null,
                'status' => 'pending',
                'expiresAt' => '2026-09-14T13:00:00+07:00',
            ],
        ]);
    });
    $context = paymentGatewayTestContext();

    $response = $this->postJson(paymentStoreRoute($context), [
        'method' => PaymentMethod::Gateway->value,
        'amount_rupiah' => 50000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => 'qris',
    ])->assertCreated()
        ->assertJsonPath('data.method', PaymentMethod::Gateway->value)
        ->assertJsonPath('data.status', PaymentStatus::Pending->value)
        ->assertJsonPath('data.gateway.driver', PaymentGatewayDriver::Paywuz->value)
        ->assertJsonPath('data.gateway.payment_id', 'trx-qris-1')
        ->assertJsonPath('data.gateway.method', 'QRIS')
        ->assertJsonPath('data.gateway.payment_number', '000201010212PAYWUZQRIS');

    expect($response->json('data.qris_image_data_uri'))
        ->toBeString()
        ->toStartWith('data:image/svg+xml;base64,');

    $payment = Payment::query()->findOrFail($response->json('data.id'));
    expect($payment->gateway_driver)->toBe(PaymentGatewayDriver::Paywuz)
        ->and($payment->gateway_payment_id)->toBe('trx-qris-1')
        ->and($payment->qris_payload)->toBe('000201010212PAYWUZQRIS');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === PAYWUZ_TEST_BASE_URL.'/transactions'
        && $request->hasHeader('Authorization', 'Bearer '.PAYWUZ_TEST_API_KEY)
        && $request['orderId'] === $payment->payment_number
        && $request['amount'] === 50000
        && $request['paymentMethod'] === 'QRIS'
        && $request['metadata']['payment_id'] === $payment->id);
});

it('creates and persists a direct Paywuz virtual account number', function (): void {
    configurePaywuz();
    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response(paywuzPaymentMethodsPayload());
        }

        $payload = $request->data();

        return Http::response([
            'data' => [
                'id' => 'trx-bni-1',
                'orderId' => $payload['orderId'],
                'amount' => 50000,
                'totalPayment' => 54000,
                'paymentMethod' => 'BNIVA',
                'paymentNumber' => '1234567890123456',
                'paymentUrl' => null,
                'status' => 'pending',
                'expiresAt' => '2026-09-14T13:00:00+07:00',
            ],
        ]);
    });
    $context = paymentGatewayTestContext();

    $this->postJson(paymentStoreRoute($context), [
        'method' => PaymentMethod::Gateway->value,
        'amount_rupiah' => 50000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => 'BNIVA',
    ])->assertCreated()
        ->assertJsonPath('data.qris_payload', null)
        ->assertJsonPath('data.qris_image_data_uri', null)
        ->assertJsonPath('data.gateway.method', 'BNIVA')
        ->assertJsonPath('data.gateway.payment_number', '1234567890123456');

    $this->assertDatabaseHas('payments', [
        'order_id' => $context['order']->id,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => 'BNIVA',
        'gateway_payment_number' => '1234567890123456',
    ]);
});

it('rejects Paywuz meta methods without contacting the provider', function (string $method): void {
    configurePaywuz();
    Http::preventStrayRequests();
    $context = paymentGatewayTestContext();

    $this->postJson(paymentStoreRoute($context), [
        'method' => PaymentMethod::Gateway->value,
        'amount_rupiah' => 50000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => $method,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('gateway_method');

    $this->assertDatabaseCount('payments', 0);
    Http::assertNothingSent();
})->with(['VA', 'ALL']);

it('rolls back the local payment when Paywuz rejects payment creation', function (): void {
    configurePaywuz();
    Http::fake([
        PAYWUZ_TEST_BASE_URL.'/payment-methods' => Http::response(paywuzPaymentMethodsPayload()),
        PAYWUZ_TEST_BASE_URL.'/transactions' => Http::response(['message' => 'provider error'], 500),
    ]);
    $context = paymentGatewayTestContext();

    $this->postJson(paymentStoreRoute($context), [
        'method' => PaymentMethod::Gateway->value,
        'amount_rupiah' => 50000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => 'QRIS',
    ])->assertStatus(502)
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_PROVIDER_ERROR');

    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('payment_status_events', 0);
});

it('rolls back the local payment when Paywuz returns an unknown transaction status', function (): void {
    configurePaywuz();
    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response(paywuzPaymentMethodsPayload());
        }

        $payload = $request->data();

        return Http::response([
            'data' => [
                'id' => 'trx-unknown-status',
                'orderId' => $payload['orderId'],
                'amount' => 50000,
                'totalPayment' => 50000,
                'paymentMethod' => 'QRIS',
                'paymentNumber' => '000201010212PAYWUZQRIS',
                'paymentUrl' => null,
                'status' => 'expired',
                'expiresAt' => '2026-09-14T13:00:00+07:00',
            ],
        ]);
    });
    $context = paymentGatewayTestContext();

    $this->postJson(paymentStoreRoute($context), [
        'method' => PaymentMethod::Gateway->value,
        'amount_rupiah' => 50000,
        'gateway_driver' => PaymentGatewayDriver::Paywuz->value,
        'gateway_method' => 'QRIS',
    ])->assertStatus(502)
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_PROVIDER_ERROR');

    $this->assertDatabaseCount('payments', 0);
});

it('rejects an invalid Paywuz webhook signature before mutating payment data', function (): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    postPaywuzWebhook($payment, 'delivery-invalid-signature', signature: 'sha256=invalid')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_WEBHOOK_INVALID');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_status)->toBe('pending');
    $this->assertDatabaseCount('payment_gateway_deliveries', 0);
});

it('processes a signed paid webhook once and deduplicates repeated deliveries', function (): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    postPaywuzWebhook($payment, 'delivery-paid-1')
        ->assertOk()
        ->assertJsonPath('received', true)
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('status', PaymentStatus::Paid->value);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->gateway_status)->toBe('success')
        ->and($payment->settled_at)->not->toBeNull();
    $this->assertDatabaseHas('payment_status_events', [
        'payment_id' => $payment->id,
        'actor_name_snapshot' => 'Paywuz',
        'status' => PaymentStatus::Paid->value,
    ]);

    postPaywuzWebhook($payment, 'delivery-paid-1')
        ->assertOk()
        ->assertJsonPath('duplicate', true)
        ->assertJsonPath('status', PaymentStatus::Paid->value);

    expect(PaymentGatewayDelivery::query()->count())->toBe(1)
        ->and($payment->statusEvents()->count())->toBe(1);
});

it('rejects Paywuz webhooks whose payment identity or amount does not match', function (
    array $overrides,
): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    postPaywuzWebhook($payment, 'delivery-mismatch-1', $overrides)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_WEBHOOK_MISMATCH');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_status)->toBe('pending');
    $this->assertDatabaseCount('payment_gateway_deliveries', 0);
})->with([
    'provider payment ID mismatch' => [['id' => 'trx-different']],
    'amount mismatch' => [['amount' => 51000]],
]);

it('rejects a signed Paywuz webhook when its event and status do not match', function (): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    postPaywuzWebhook($payment, 'delivery-event-status-mismatch', [
        'event' => 'transaction.paid',
        'status' => 'failed',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_WEBHOOK_MISMATCH');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    $this->assertDatabaseCount('payment_gateway_deliveries', 0);
});

it('rejects unknown Paywuz webhook events and statuses', function (
    array $overrides,
    ?string $headerEvent,
): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    postPaywuzWebhook(
        $payment,
        'delivery-unknown-contract',
        $overrides,
        headerEvent: $headerEvent,
    )->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_WEBHOOK_MISMATCH');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    $this->assertDatabaseCount('payment_gateway_deliveries', 0);
})->with([
    'unknown event' => [['event' => 'transaction.expired'], 'transaction.expired'],
    'unknown status' => [['status' => 'expired'], null],
]);

it('rejects a reused Paywuz delivery ID for a different event', function (): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    postPaywuzWebhook($payment, 'delivery-reused-event')->assertOk();

    postPaywuzWebhook($payment, 'delivery-reused-event', [
        'event' => 'transaction.cancelled',
        'status' => 'cancelled',
        'paidAt' => null,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_GATEWAY_WEBHOOK_MISMATCH');

    expect(PaymentGatewayDelivery::query()->count())->toBe(1);
});

it('prevents manual reconciliation of provider-managed gateway payments', function (): void {
    configurePaywuz();
    $context = paymentGatewayTestContext();
    $payment = gatewayPayment($context);

    $this->postJson(route('api.v1.organizations.branches.orders.payments.status-events.store', [
        'organization' => $context['organization'],
        'branch' => $context['branch'],
        'order' => $context['order'],
        'payment' => $payment,
    ]), [
        'status' => PaymentStatus::Paid->value,
    ])->assertConflict()
        ->assertJsonPath('code', 'INVALID_PAYMENT_STATUS_TRANSITION')
        ->assertJsonPath(
            'message',
            'Status pembayaran payment gateway hanya dapat diperbarui oleh penyedia.',
        );

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});
