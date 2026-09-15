<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('returns a stable digital receipt with a signed public share URL', function (): void {
    $this->withoutVite();
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'name' => 'Cabang Kemang',
        'address' => 'Jl. Kemang Raya No. 10',
    ]);
    $customer = User::factory()->create(['name' => 'Budi']);
    $customer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'customer_name_snapshot' => 'Budi',
        'items_subtotal_rupiah' => 24000,
        'delivery_fee_rupiah' => 3000,
        'total_rupiah' => 27000,
    ]);
    OrderItem::factory()->for($order)->create([
        'service_name_snapshot' => 'Cuci Kering Lipat',
        'requested_weight_grams' => 2000,
        'chargeable_weight_grams' => 2000,
        'subtotal_rupiah' => 24000,
    ]);
    Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Cash,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 27000,
    ]);
    Sanctum::actingAs($customer);

    $response = $this->getJson(route('api.v1.organizations.branches.orders.receipt.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.receipt_number', $order->order_number)
        ->assertJsonPath('data.branch.name', 'Cabang Kemang')
        ->assertJsonPath('data.customer.name', 'Budi')
        ->assertJsonPath('data.items.0.service.name', 'Cuci Kering Lipat')
        ->assertJsonPath('data.pricing.total_rupiah', 27000)
        ->assertJsonPath('data.pricing.paid_rupiah', 27000)
        ->assertJsonPath('data.pricing.outstanding_rupiah', 0)
        ->assertJsonPath('data.payments.0.status', PaymentStatus::Paid->value)
        ->assertJsonStructure(['data' => ['share_url']]);

    $shareUrl = $response->json('data.share_url');

    expect($shareUrl)->toBeString();
    expect(URL::hasValidSignature(Request::create($shareUrl)))->toBeTrue()
        ->and($shareUrl)->not->toContain('expires=');
});

it('prevents a customer from reading another customer receipt', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $otherCustomer = User::factory()->create();
    $otherCustomer->assignRole(RoleName::Customer->value);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);
    Sanctum::actingAs($otherCustomer);

    $this->getJson(route('api.v1.organizations.branches.orders.receipt.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]))->assertForbidden();
});
