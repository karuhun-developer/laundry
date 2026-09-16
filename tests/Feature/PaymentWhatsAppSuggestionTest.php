<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{Organization, Branch, Order, Payment, User} */
function createPaymentWhatsAppSuggestionContext(?string $branchPhone = '0812-3456-7890'): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'name' => 'Cabang Dago',
        'phone' => $branchPhone,
    ]);
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
        'order_number' => 'LDR-20260915-00000001',
    ]);
    $payment = Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'payment_number' => 'PAY-20260915-00000001',
        'method' => PaymentMethod::Qris,
        'status' => PaymentStatus::Pending,
        'amount_rupiah' => 125000,
        'settled_at' => null,
    ]);

    return [$organization, $branch, $order, $payment, $customer];
}

function paymentWhatsAppSuggestionRoute(
    Organization $organization,
    Branch $branch,
    Order $order,
    Payment $payment,
): string {
    return route('api.v1.organizations.branches.orders.payments.whatsapp-suggestion.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
        'payment' => $payment,
    ]);
}

it('returns an RFC3986 wa.me suggestion with payment verification details', function (): void {
    [$organization, $branch, $order, $payment, $customer] = createPaymentWhatsAppSuggestionContext();
    Sanctum::actingAs($customer);

    $response = $this->getJson(
        paymentWhatsAppSuggestionRoute($organization, $branch, $order, $payment),
    )->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.phone', '6281234567890')
        ->assertJsonPath('data.template_id', null)
        ->assertJsonPath('data.unavailable_reason', null);

    $message = $response->json('data.message');
    $url = $response->json('data.url');

    expect($message)->toContain('LDR-20260915-00000001')
        ->toContain('PAY-20260915-00000001')
        ->toContain('Metode pembayaran: QRIS')
        ->toContain('Nominal: Rp125.000')
        ->toContain('meminta verifikasi pembayaran')
        ->and($url)->toStartWith('https://wa.me/6281234567890?text=')
        ->toContain('%20')
        ->not->toContain('+');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['text'] ?? null)->toBe($message);
});

it('normalizes supported legacy branch phone formats', function (string $phone): void {
    [$organization, $branch, $order, $payment, $customer] = createPaymentWhatsAppSuggestionContext($phone);
    Sanctum::actingAs($customer);

    $this->getJson(paymentWhatsAppSuggestionRoute($organization, $branch, $order, $payment))
        ->assertOk()
        ->assertJsonPath('data.phone', '6281234567890')
        ->assertJsonPath('data.available', true);
})->with([
    'local format' => '081234567890',
    'international plus format' => '+62 812-3456-7890',
    'international digits format' => '6281234567890',
]);

it('returns a clear unavailable result when the branch phone is missing or invalid', function (?string $phone): void {
    [$organization, $branch, $order, $payment, $customer] = createPaymentWhatsAppSuggestionContext($phone);
    Sanctum::actingAs($customer);

    $this->getJson(paymentWhatsAppSuggestionRoute($organization, $branch, $order, $payment))
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.message', null)
        ->assertJsonPath('data.url', null)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Nomor WhatsApp cabang belum tersedia atau tidak valid.',
        );
})->with([
    'missing phone' => null,
    'invalid phone' => '12345',
]);

it('returns an RFC3986 wa.me suggestion for branch admin to share payment to customer', function (): void {
    [$organization, $branch, $order, $payment, $customer] = createPaymentWhatsAppSuggestionContext();
    $order->update([
        'customer_name_snapshot' => 'Budi Pelanggan',
        'customer_whatsapp_phone_snapshot' => '0812-9876-5432',
    ]);

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);

    $response = $this->getJson(
        paymentWhatsAppSuggestionRoute($organization, $branch, $order, $payment),
    )->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.phone', '6281298765432')
        ->assertJsonPath('data.template_id', null)
        ->assertJsonPath('data.unavailable_reason', null);

    $message = $response->json('data.message');
    $url = $response->json('data.url');

    expect($message)->toContain('Halo Budi Pelanggan')
        ->toContain('LDR-20260915-00000001')
        ->toContain('PAY-20260915-00000001')
        ->toContain('Metode pembayaran: QRIS')
        ->toContain('Nominal: Rp125.000')
        ->toContain('Silakan selesaikan pembayaran melalui QRIS.')
        ->toContain('pesanan/LDR-20260915-00000001')
        ->and($url)->toStartWith('https://wa.me/6281298765432?text=')
        ->toContain('%20')
        ->not->toContain('+');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['text'] ?? null)->toBe($message);
});

it('returns unavailable when branch admin requests suggestion and customer phone is missing or invalid', function (?string $phone): void {
    [$organization, $branch, $order, $payment, $customer] = createPaymentWhatsAppSuggestionContext();
    $order->update([
        'customer_whatsapp_phone_snapshot' => $phone,
    ]);

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(paymentWhatsAppSuggestionRoute($organization, $branch, $order, $payment))
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.message', null)
        ->assertJsonPath('data.url', null)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
        );
})->with([
    'missing phone' => null,
    'invalid phone' => '12345',
]);

it('enforces authentication, customer ownership, role, and nested bindings', function (): void {
    [$organization, $branch, $order, $payment, $customer] = createPaymentWhatsAppSuggestionContext();
    $route = paymentWhatsAppSuggestionRoute($organization, $branch, $order, $payment);

    $this->getJson($route)->assertUnauthorized();

    $otherCustomer = User::factory()->create();
    $otherCustomer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($otherCustomer);
    $this->getJson($route)->assertForbidden();

    $otherOrgBranchAdmin = User::factory()->create();
    $otherOrgBranchAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($otherOrgBranchAdmin);
    $this->getJson($route)->assertForbidden();

    $unassignedBranchAdmin = User::factory()->for($organization)->create();
    $unassignedBranchAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($unassignedBranchAdmin);
    $this->getJson($route)->assertForbidden();

    Sanctum::actingAs($customer);
    $otherBranch = Branch::factory()->for($organization)->create();
    $this->getJson(paymentWhatsAppSuggestionRoute($organization, $otherBranch, $order, $payment))
        ->assertNotFound();

    $otherOrder = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);
    $this->getJson(paymentWhatsAppSuggestionRoute($organization, $branch, $otherOrder, $payment))
        ->assertNotFound();

    $otherOrganization = Organization::factory()->create();
    $this->getJson(paymentWhatsAppSuggestionRoute($otherOrganization, $branch, $order, $payment))
        ->assertNotFound();
});

it('normalizes branch phones on create and update and rejects invalid numbers', function (): void {
    $organization = Organization::factory()->create();
    $city = City::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)->post(route('cms.branches.store'), [
        'city_id' => $city->id,
        'name' => 'Cabang WhatsApp',
        'code' => 'WAP01',
        'phone' => '+62 812-3456-7890',
        'address' => 'Jl. Dago No. 1',
        'delivery_fee_per_km_rupiah' => 3000,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $branch = Branch::query()->where('code', 'WAP01')->firstOrFail();
    expect($branch->phone)->toBe('6281234567890');

    $this->actingAs($owner)->patch(route('cms.branches.update', $branch), [
        'city_id' => $city->id,
        'name' => 'Cabang WhatsApp',
        'code' => 'WAP01',
        'phone' => '0813-4567-8901',
        'address' => 'Jl. Dago No. 1',
        'delivery_fee_per_km_rupiah' => 3000,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($branch->refresh()->phone)->toBe('6281345678901');

    $this->actingAs($owner)->patch(route('cms.branches.update', $branch), [
        'city_id' => $city->id,
        'name' => 'Cabang WhatsApp',
        'code' => 'WAP01',
        'phone' => '12345',
        'address' => 'Jl. Dago No. 1',
        'delivery_fee_per_km_rupiah' => 3000,
    ])->assertSessionHasErrors([
        'phone' => 'Nomor WhatsApp harus berupa nomor seluler Indonesia yang valid.',
    ]);

    expect($branch->refresh()->phone)->toBe('6281345678901');
});
