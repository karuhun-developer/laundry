<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createOrderPricingContext(): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'latitude' => 0,
        'longitude' => 0,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3000,
    ]);
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->forAllBranches()->create([
        'price_per_kilogram_rupiah' => 12000,
        'minimum_chargeable_grams' => 3000,
    ]);
    $customer = User::factory()->create([
        'name' => 'Budi',
        'whatsapp_phone' => '6281234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'latitude' => 0.1,
        'longitude' => 0,
    ]);
    Sanctum::actingAs($customer);

    $orderId = test()->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_address_id' => $address->id,
        'items' => [['price_option_id' => $priceOption->id]],
        'delivery' => ['latitude' => 0.1, 'longitude' => 0],
    ])->assertCreated()->json('data.id');

    $order = Order::query()->findOrFail($orderId);
    $branchAdmin = User::factory()->for($organization)->create(['name' => 'Admin Cabang']);
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);

    return [$organization, $branch, $service, $priceOption, $order, $branchAdmin, $customer];
}

function orderPricingRoute(Organization $organization, Branch $branch, Order $order): string
{
    return route('api.v1.organizations.branches.orders.pricing.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);
}

it('starts the duration snapshot at confirmation and closes the received pickup', function (): void {
    [$organization, $branch, $service, $priceOption, $order, $branchAdmin] = createOrderPricingContext();
    expect($order->estimated_ready_at)->toBeNull();
    expect($order->completion_duration_hours_snapshot)->toBeNull();
    $service->update(['completion_duration_hours' => 24]);
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(10, 0));
    Sanctum::actingAs($branchAdmin);

    $this->postJson(orderPricingRoute($organization, $branch, $order), [
        'price_option_id' => $priceOption->id, 'weight_grams' => 3500,
    ])->assertOk()->assertJsonPath('data.completion_duration_hours_snapshot', 24)
        ->assertJsonPath('data.tasks.0.status', 'completed');

    $service->update(['completion_duration_hours' => 96]);
    expect($order->fresh()->estimated_ready_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 10:00:00');
    expect($order->fresh()->completion_duration_hours_snapshot)->toBe(24);
    $this->assertDatabaseHas('order_operation_events', ['order_id' => $order->id, 'status' => 'completed', 'actor_id' => $branchAdmin->id]);
});

it('lets assigned branch staff confirm actual weight and server-calculated pricing', function (): void {
    [$organization, $branch, , $priceOption, $order, $branchAdmin] = createOrderPricingContext();
    Sanctum::actingAs($branchAdmin);

    $this->postJson(orderPricingRoute($organization, $branch, $order), [
        'price_option_id' => $priceOption->id,
        'weight_grams' => 3500,
    ])->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Weighed->value)
        ->assertJsonPath('data.pricing.confirmed', true)
        ->assertJsonPath('data.items.0.calculation.requested_weight_grams', 3500)
        ->assertJsonPath('data.items.0.calculation.chargeable_weight_grams', 3500)
        ->assertJsonPath('data.pricing.items_subtotal_rupiah', 42000)
        ->assertJsonPath('data.pricing.delivery_fee_rupiah', 6000)
        ->assertJsonPath('data.pricing.total_rupiah', 48000)
        ->assertJsonPath('data.payment_summary.outstanding_rupiah', 48000)
        ->assertJsonPath('data.status_history.1.status', OrderStatus::Weighed->value);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => OrderStatus::Weighed->value,
        'items_subtotal_rupiah' => 42000,
        'delivery_fee_rupiah' => 6000,
        'total_rupiah' => 48000,
    ]);
    expect($order->fresh()?->priced_at)->not->toBeNull();
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'requested_weight_grams' => 3500,
        'subtotal_rupiah' => 42000,
    ]);
    $this->assertDatabaseHas('activity_log', [
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'description' => 'order pricing confirmed',
    ]);
});

it('allows switching service package and custom price per kilogram within floor and ceiling bounds', function (): void {
    [$organization, $branch, $service, , $order, $branchAdmin] = createOrderPricingContext();
    $expressOption = PriceOption::factory()->for($service)->for($branch)->create([
        'name' => 'Kiloan express',
        'price_per_kilogram_rupiah' => 15000,
        'min_price_per_kilogram_rupiah' => 12000,
        'minimum_chargeable_grams' => 2000,
    ]);
    Sanctum::actingAs($branchAdmin);
    $route = orderPricingRoute($organization, $branch, $order);

    // Reject price below floor
    $this->postJson($route, [
        'price_option_id' => $expressOption->id,
        'weight_grams' => 2000,
        'custom_price_per_kilogram_rupiah' => 11000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['custom_price_per_kilogram_rupiah']);

    // Accept custom price at or above floor
    $this->postJson($route, [
        'price_option_id' => $expressOption->id,
        'weight_grams' => 2000,
        'custom_price_per_kilogram_rupiah' => 13000,
    ])->assertOk()
        ->assertJsonPath('data.items.0.price_option.name', 'Kiloan express')
        ->assertJsonPath('data.items.0.price_option.pricing_snapshot.price_per_kilogram_rupiah', 13000)
        ->assertJsonPath('data.items.0.price_option.pricing_snapshot.min_price_per_kilogram_rupiah', 12000)
        ->assertJsonPath('data.items.0.calculation.subtotal_rupiah', 26000);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'price_option_id' => $expressOption->id,
        'price_per_kilogram_rupiah_snapshot' => 13000,
        'min_price_per_kilogram_rupiah_snapshot' => 12000,
        'subtotal_rupiah' => 26000,
    ]);

    // Reconfirmation is rejected once confirmed
    $this->postJson($route, [
        'price_option_id' => $expressOption->id,
        'weight_grams' => 2500,
    ])->assertConflict()
        ->assertJsonPath('code', 'ORDER_PRICING_CANNOT_BE_CONFIRMED');

    expect($order->statusEvents()->count())->toBe(2);
});

it('rejects invalid, inactive, cross-branch, and cross-tenant pricing input', function (): void {
    [$organization, $branch, , $priceOption, $order, $branchAdmin] = createOrderPricingContext();
    Sanctum::actingAs($branchAdmin);
    $route = orderPricingRoute($organization, $branch, $order);

    $this->postJson($route, [
        'price_option_id' => $priceOption->id,
        'weight_grams' => 0,
        'total_rupiah' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['weight_grams', 'total_rupiah']);

    $priceOption->update(['is_active' => false]);
    $this->postJson($route, [
        'price_option_id' => $priceOption->id,
        'weight_grams' => 2000,
    ])->assertNotFound();

    $priceOption->update(['is_active' => true]);
    $otherBranch = Branch::factory()->for($organization)->create();
    $otherOption = PriceOption::factory()->for($priceOption->service)->for($otherBranch)->create();
    $this->postJson($route, [
        'price_option_id' => $otherOption->id,
        'weight_grams' => 2000,
    ])->assertNotFound();

    $otherOrganization = Organization::factory()->create();
    $otherTenantBranch = Branch::factory()->for($otherOrganization)->create();
    $otherService = Service::factory()->for($otherOrganization)->create();
    $otherTenantOption = PriceOption::factory()->for($otherService)->for($otherTenantBranch)->create();
    $this->postJson($route, [
        'price_option_id' => $otherTenantOption->id,
        'weight_grams' => 2000,
    ])->assertNotFound();

    expect($order->fresh()?->status)->toBe(OrderStatus::PickupRequested);
});

it('enforces authentication, customer read-only access, branch assignment, and scoped bindings', function (): void {
    [$organization, $branch, , $priceOption, $order, $branchAdmin, $customer] = createOrderPricingContext();
    $route = orderPricingRoute($organization, $branch, $order);
    $payload = ['price_option_id' => $priceOption->id, 'weight_grams' => 2000];

    auth()->forgetGuards();
    $this->postJson($route, $payload)->assertUnauthorized();

    Sanctum::actingAs($customer);
    $this->postJson($route, $payload)->assertForbidden();

    $unassignedAdmin = User::factory()->for($organization)->create();
    $unassignedAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($unassignedAdmin);
    $this->postJson($route, $payload)->assertForbidden();

    $otherBranch = Branch::factory()->for($organization)->create();
    $branchAdmin->branches()->attach($otherBranch);
    Sanctum::actingAs($branchAdmin);
    $this->postJson(orderPricingRoute($organization, $otherBranch, $order), $payload)
        ->assertNotFound();

    $otherOrganization = Organization::factory()->create();
    $this->postJson(orderPricingRoute($otherOrganization, $branch, $order), $payload)
        ->assertNotFound();

    expect($order->fresh()?->status)->toBe(OrderStatus::PickupRequested);
});
