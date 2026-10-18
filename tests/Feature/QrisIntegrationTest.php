<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\Qris\QrisConfigurationResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

const QRIS_TEST_KEY = 'qris_a00470db65b730b967bb35cf3ae6c93c7eab7e947a078608';
const QRIS_SAMPLE_STRING = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('resolves qris configuration falling back from branch to organization', function (): void {
    $resolver = app(QrisConfigurationResolver::class);

    $organization = Organization::factory()->create([
        'qris_enabled' => true,
        'qris_api_key' => 'org-api-key',
        'qris_base_url' => 'https://qris.karuhundeveloper.com',
    ]);

    // Branch with no custom key falls back to organization
    $branchWithoutKey = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_api_key' => null,
    ]);

    $config = $resolver->resolve($branchWithoutKey);
    expect($config)->not->toBeNull()
        ->and($config->apiKey)->toBe('org-api-key')
        ->and($config->baseUrl)->toBe('https://qris.karuhundeveloper.com');

    // Branch with custom key overrides organization
    $branchWithKey = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_api_key' => 'branch-custom-key',
    ]);

    $overrideConfig = $resolver->resolve($branchWithKey);
    expect($overrideConfig)->not->toBeNull()
        ->and($overrideConfig->apiKey)->toBe('branch-custom-key');

    // Disabled branch returns null
    $disabledBranch = Branch::factory()->for($organization)->create([
        'qris_enabled' => false,
    ]);
    expect($resolver->resolve($disabledBranch))->toBeNull();
});

it('creates dynamic QRIS with 15-minute expiration and token from qris api', function (): void {
    $organization = Organization::factory()->create([
        'qris_enabled' => true,
        'qris_api_key' => QRIS_TEST_KEY,
    ]);
    $branch = Branch::factory()->for($organization)->create([
        'qris_enabled' => true,
        'qris_api_key' => null, // fallback to org
    ]);

    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);

    $order = Order::factory()->for($organization)->for($branch)->create([
        'total_rupiah' => 60000,
        'items_subtotal_rupiah' => 60000,
    ]);

    Http::fake([
        'https://qris.karuhundeveloper.com/v1/qris/create' => Http::response([
            'status' => 'success',
            'data' => [
                'token' => 'tok_qris_mock_123',
                'amount' => 60000,
                'unique_code' => 123,
                'qris_string' => QRIS_SAMPLE_STRING,
                'expires_at' => Carbon::now('UTC')->addMinutes(15)->toISOString(),
                'expires_in_seconds' => 900,
            ],
        ], 200),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson(route('api.v1.organizations.branches.orders.payments.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]), [
        'method' => PaymentMethod::Qris->value,
        'amount_rupiah' => 60000,
    ])->assertCreated();

    $payment = Payment::query()->findOrFail($response->json('data.id'));
    expect($payment->qris_token)->toBe('tok_qris_mock_123')
        ->and($payment->qris_unique_code)->toBe(123)
        ->and($payment->qris_payload)->toBe(QRIS_SAMPLE_STRING)
        ->and($payment->qris_expires_at)->not->toBeNull()
        ->and($payment->qris_expires_at->isPast())->toBeFalse()
        ->and($payment->qris_expires_at->isFuture())->toBeTrue();
});

it('checks payment status via api and settles if paid on gateway', function (): void {
    $organization = Organization::factory()->create([
        'qris_enabled' => true,
        'qris_api_key' => QRIS_TEST_KEY,
    ]);
    $branch = Branch::factory()->for($organization)->create(['qris_enabled' => true]);
    $order = Order::factory()->for($organization)->for($branch)->create(['total_rupiah' => 50000]);

    $payment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 50000,
        'qris_token' => 'tok_test_pending_1',
        'qris_expires_at' => Carbon::now()->addMinutes(10),
    ]);

    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    Sanctum::actingAs($admin);

    Http::fake([
        'https://qris.karuhundeveloper.com/v1/payment/check' => Http::response([
            'status' => 'success',
            'data' => [
                'token' => 'tok_test_pending_1',
                'payment_status' => 'paid',
                'settled_at' => Carbon::now()->toIso8601String(),
            ],
        ], 200),
    ]);

    $checkUrl = route('api.v1.organizations.branches.orders.payments.check', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
        'payment' => $payment,
    ]);

    $response = $this->postJson($checkUrl);
    $response->assertOk()
        ->assertJsonPath('paid', true)
        ->assertJsonPath('status', 'paid');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->settled_at)->not->toBeNull();
});

it('regenerates an expired qris payment', function (): void {
    $organization = Organization::factory()->create([
        'qris_enabled' => true,
        'qris_api_key' => QRIS_TEST_KEY,
    ]);
    $branch = Branch::factory()->for($organization)->create(['qris_enabled' => true]);
    $order = Order::factory()->for($organization)->for($branch)->create(['total_rupiah' => 50000]);

    $payment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 50000,
        'qris_token' => 'tok_old_expired',
        'qris_payload' => 'OLD_PAYLOAD',
        'qris_expires_at' => Carbon::now()->subMinutes(5), // expired
    ]);

    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    Sanctum::actingAs($admin);

    Http::fake([
        'https://qris.karuhundeveloper.com/v1/qris/create' => Http::response([
            'status' => 'success',
            'data' => [
                'token' => 'tok_new_regenerated',
                'amount' => 50000,
                'unique_code' => 555,
                'qris_string' => QRIS_SAMPLE_STRING,
                'expires_at' => Carbon::now('UTC')->addMinutes(15)->toISOString(),
                'expires_in_seconds' => 900,
            ],
        ], 200),
    ]);

    $regenUrl = route('api.v1.organizations.branches.orders.payments.regenerate', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
        'payment' => $payment,
    ]);

    $response = $this->postJson($regenUrl);
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('qris_payload', QRIS_SAMPLE_STRING);

    expect($response->json('remaining_seconds'))->toBeGreaterThanOrEqual(895)->toBeLessThanOrEqual(900);

    $payment->refresh();
    expect($payment->qris_token)->toBe('tok_new_regenerated')
        ->and($payment->qris_unique_code)->toBe(555)
        ->and($payment->qris_payload)->toBe(QRIS_SAMPLE_STRING)
        ->and($payment->qris_expires_at->isPast())->toBeFalse()
        ->and($payment->qris_expires_at->isFuture())->toBeTrue();
});

it('reconciles pending payments via artisan qris:reconcile command', function (): void {
    $organization = Organization::factory()->create([
        'qris_enabled' => true,
        'qris_api_key' => QRIS_TEST_KEY,
    ]);
    $branch = Branch::factory()->for($organization)->create(['qris_enabled' => true]);
    $order = Order::factory()->for($organization)->for($branch)->create(['total_rupiah' => 30000]);

    $payment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 30000,
        'qris_token' => 'tok_reconcile_cron_1',
        'qris_expires_at' => Carbon::now()->addMinutes(10),
    ]);

    Http::fake([
        'https://qris.karuhundeveloper.com/v1/payment/check' => Http::response([
            'status' => 'success',
            'data' => [
                'token' => 'tok_reconcile_cron_1',
                'payment_status' => 'paid',
                'settled_at' => Carbon::now()->toIso8601String(),
            ],
        ], 200),
    ]);

    $this->artisan('qris:reconcile')
        ->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->settled_at)->not->toBeNull();
});
