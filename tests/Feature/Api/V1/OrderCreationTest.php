<?php

use App\Enums\OrderStatus;
use App\Enums\PriceType;
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

it('lets a customer request pickup for one service without entering weight or seeing a final price', function (): void {
    $this->travelTo('2026-09-13 10:00:00');
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
        'label' => 'Rumah',
        'recipient_name' => 'Budi',
        'whatsapp_phone' => '6281234567890',
        'address' => 'Jl. Melati No. 10',
        'notes' => 'Pagar biru',
        'latitude' => 0.1,
        'longitude' => 0,
    ]);
    Sanctum::actingAs($customer);

    $response = $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_address_id' => $address->id,
        'items' => [[
            'price_option_id' => $priceOption->id,
        ]],
        'delivery' => [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order_number', 'LDR-20260913-00000001')
        ->assertJsonPath('data.status', OrderStatus::PickupRequested->value)
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.items.0.calculation.requested_weight_grams', 0)
        ->assertJsonPath('data.items.0.calculation.chargeable_weight_grams', 0)
        ->assertJsonPath('data.items.0.calculation.subtotal_rupiah', 0)
        ->assertJsonPath('data.pricing.confirmed', false)
        ->assertJsonPath('data.pricing.confirmed_at', null)
        ->assertJsonPath('data.pricing.items_subtotal_rupiah', 0)
        ->assertJsonPath('data.pricing.delivery_fee_rupiah', 6000)
        ->assertJsonPath('data.pricing.total_rupiah', 0)
        ->assertJsonPath('data.delivery.free_radius_meters_snapshot', 10000)
        ->assertJsonPath('data.delivery.fee_per_km_rupiah_snapshot', 3000)
        ->assertJsonPath('data.delivery.customer_latitude', 0.1)
        ->assertJsonPath('data.delivery.address.label', 'Rumah')
        ->assertJsonPath('data.delivery.address.address', 'Jl. Melati No. 10')
        ->assertJsonPath('data.status_history.0.status', OrderStatus::PickupRequested->value);

    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'customer_address_id' => $address->id,
        'created_by_id' => $customer->id,
        'customer_whatsapp_phone_snapshot' => '6281234567890',
        'status' => OrderStatus::PickupRequested->value,
        'items_subtotal_rupiah' => 0,
        'delivery_fee_rupiah' => 6000,
        'total_rupiah' => 0,
        'priced_at' => null,
        'pickup_label_snapshot' => 'Rumah',
        'pickup_address_snapshot' => 'Jl. Melati No. 10',
    ]);

    $customer->update(['whatsapp_phone' => '6289876543210']);

    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'customer_whatsapp_phone_snapshot' => '6281234567890',
    ]);
    $this->assertDatabaseHas('order_items', [
        'price_option_id' => $priceOption->id,
        'requested_weight_grams' => 0,
        'chargeable_weight_grams' => 0,
        'subtotal_rupiah' => 0,
    ]);
    $this->assertDatabaseHas('order_status_events', [
        'actor_id' => $customer->id,
        'status' => OrderStatus::PickupRequested->value,
    ]);
    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);
    expect($customer->customerOrganizations()->whereKey($organization->id)->count())->toBe(1);
    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'orders',
        'description' => 'order created',
        'subject_type' => Order::class,
    ]);
});

it('lets an assigned branch admin create a fixed-package order for a customer', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->fixedPackage()->for($service)->for($branch)->create();
    $customer = User::factory()->for($organization)->create();
    $customer->assignRole(RoleName::Customer->value);
    $customer->customerOrganizations()->attach($organization);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);

    $response = $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_id' => $customer->id,
        'items' => [[
            'price_option_id' => $priceOption->id,
            'weight_grams' => 12500,
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.items.0.price_option.type', PriceType::FixedPackage->value)
        ->assertJsonPath('data.items.0.calculation.excess_weight_grams', 500)
        ->assertJsonPath('data.items.0.calculation.excess_price_rupiah', 6000)
        ->assertJsonPath('data.pricing.total_rupiah', 126000)
        ->assertJsonPath('data.delivery', null);

    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'created_by_id' => $branchAdmin->id,
        'total_rupiah' => 126000,
    ]);

    expect(Order::query()->latest('id')->firstOrFail()->priced_at)->not->toBeNull();
});

it('preserves catalog and delivery snapshots for staff-created orders after configuration changes', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'latitude' => 0,
        'longitude' => 0,
        'free_delivery_radius_meters' => 10000,
        'delivery_fee_per_km_rupiah' => 3000,
    ]);
    $service = Service::factory()->for($organization)->create([
        'name' => 'Cuci Lama',
        'code' => 'LAMA',
    ]);
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create([
        'name' => 'Tarif Lama',
        'price_per_kilogram_rupiah' => 12000,
        'minimum_chargeable_grams' => 1000,
    ]);
    $customer = User::factory()->for($organization)->create();
    $customer->assignRole(RoleName::Customer->value);
    $customer->customerOrganizations()->attach($organization);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);

    $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_id' => $customer->id,
        'items' => [[
            'price_option_id' => $priceOption->id,
            'weight_grams' => 2000,
        ]],
        'delivery' => ['latitude' => 0.1, 'longitude' => 0],
    ])->assertCreated();

    $service->update(['name' => 'Cuci Baru', 'code' => 'BARU']);
    $priceOption->update(['name' => 'Tarif Baru', 'price_per_kilogram_rupiah' => 30000]);
    $branch->update([
        'free_delivery_radius_meters' => 20000,
        'delivery_fee_per_km_rupiah' => 9000,
    ]);

    $this->assertDatabaseHas('order_items', [
        'service_name_snapshot' => 'Cuci Lama',
        'service_code_snapshot' => 'LAMA',
        'price_option_name_snapshot' => 'Tarif Lama',
        'price_per_kilogram_rupiah_snapshot' => 12000,
        'subtotal_rupiah' => 24000,
    ]);
    $this->assertDatabaseHas('orders', [
        'delivery_free_radius_meters_snapshot' => 10000,
        'delivery_fee_per_km_rupiah_snapshot' => 3000,
        'delivery_fee_rupiah' => 6000,
        'total_rupiah' => 30000,
    ]);
});

it('rejects unauthenticated and unassigned branch-admin order creation', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'latitude' => -6.2,
        'longitude' => 106.8,
    ]);
    $route = route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]);
    $payload = [
        'customer_id' => $customer->id,
        'items' => [['price_option_id' => $priceOption->id, 'weight_grams' => 1000]],
    ];

    $this->postJson($route, $payload)->assertUnauthorized();

    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($branchAdmin);

    $this->postJson($route, $payload)->assertForbidden();
    $this->assertDatabaseCount('orders', 0);
});

it('hides cross-tenant branches, prices, and customers', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $otherService = Service::factory()->for($otherOrganization)->create();
    $otherPriceOption = PriceOption::factory()->for($otherService)->for($otherBranch)->create();
    $customer = User::factory()->for($organization)->create();
    $customer->assignRole(RoleName::Customer->value);
    $customer->customerOrganizations()->attach($organization);
    $otherCustomer = User::factory()->for($otherOrganization)->create();
    $otherCustomer->assignRole(RoleName::Customer->value);
    $otherCustomer->customerOrganizations()->attach($otherOrganization);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($owner);

    $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $otherBranch,
    ]), [
        'customer_id' => $customer->id,
        'items' => [['price_option_id' => $priceOption->id, 'weight_grams' => 1000]],
    ])->assertNotFound();

    $route = route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]);

    $this->postJson($route, [
        'customer_id' => $customer->id,
        'items' => [['price_option_id' => $otherPriceOption->id, 'weight_grams' => 1000]],
    ])->assertNotFound();

    $this->postJson($route, [
        'customer_id' => $otherCustomer->id,
        'items' => [['price_option_id' => $priceOption->id, 'weight_grams' => 1000]],
    ])->assertNotFound();

    $this->assertDatabaseCount('orders', 0);
});

it('rejects staff order creation for a mixed-role customer account', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $mixedRoleCustomer = User::factory()->create();
    $mixedRoleCustomer->assignRole([RoleName::Customer->value, RoleName::Owner->value]);
    $mixedRoleCustomer->customerOrganizations()->attach($organization);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);

    $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_id' => $mixedRoleCustomer->id,
        'items' => [['price_option_id' => $priceOption->id, 'weight_grams' => 1000]],
    ])->assertNotFound();

    $this->assertDatabaseCount('orders', 0);
});

it('creates an order only while the locked customer membership still exists', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $customer->customerOrganizations()->attach($organization);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);

    $customer->customerOrganizations()->detach($organization);

    $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_id' => $customer->id,
        'items' => [['price_option_id' => $priceOption->id, 'weight_grams' => 1000]],
    ])->assertNotFound();

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseMissing('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);
});

it('does not create orders from inactive tenant or catalog records', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'latitude' => -6.2,
        'longitude' => 106.8,
    ]);
    Sanctum::actingAs($customer);
    $route = route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]);
    $payload = [
        'customer_address_id' => $address->id,
        'items' => [['price_option_id' => $priceOption->id]],
        'delivery' => ['latitude' => -6.2, 'longitude' => 106.8],
    ];

    $organization->update(['is_active' => false]);
    $this->postJson($route, $payload)->assertNotFound();

    $organization->update(['is_active' => true]);
    $branch->update(['is_active' => false]);
    $this->postJson($route, $payload)->assertNotFound();

    $branch->update(['is_active' => true]);
    $service->update(['is_active' => false]);
    $this->postJson($route, $payload)->assertNotFound();

    $service->update(['is_active' => true]);
    $priceOption->update(['is_active' => false]);
    $this->postJson($route, $payload)->assertNotFound();

    $this->assertDatabaseCount('orders', 0);
});

it('rejects customer weight, missing pickup location, multiple services, and server-owned fields', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create();
    Sanctum::actingAs($customer);
    $route = route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]);

    $this->postJson($route, [])->assertUnprocessable()
        ->assertJsonValidationErrors(['items', 'customer_address_id'])
        ->assertJsonPath('errors.customer_address_id.0', 'Alamat penjemputan wajib dipilih.')
        ->assertJsonPath('errors.items.0', 'Minimal satu item laundry wajib diisi.');

    $this->postJson($route, [
        'customer_id' => User::factory()->create()->id,
        'customer_address_id' => $address->id,
        'total_rupiah' => 1,
        'items' => [
            ['price_option_id' => $priceOption->id, 'weight_grams' => 1000],
            ['price_option_id' => $priceOption->id],
        ],
        'delivery' => ['latitude' => -6.2],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'customer_id',
            'total_rupiah',
            'items',
            'items.0.price_option_id',
            'items.0.weight_grams',
            'delivery.longitude',
        ]);

    $this->assertDatabaseCount('orders', 0);
});

it('requires branch coordinates when delivery is requested', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'latitude' => null,
        'longitude' => null,
    ]);
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'latitude' => -6.2,
        'longitude' => 106.8,
    ]);
    Sanctum::actingAs($customer);

    $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_address_id' => $address->id,
        'items' => [['price_option_id' => $priceOption->id]],
        'delivery' => ['latitude' => -6.2, 'longitude' => 106.8],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('delivery')
        ->assertJsonPath(
            'errors.delivery.0',
            'Cabang belum memiliki koordinat untuk menghitung biaya antar jemput.',
        );

    $this->assertDatabaseCount('orders', 0);
});

it('prevents duplicate active pickup requests and permits another request after processing starts', function (): void {
    $this->travelTo('2026-09-15 10:00:00');
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create();
    Sanctum::actingAs($customer);
    $route = route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]);
    $payload = [
        'customer_address_id' => $address->id,
        'items' => [['price_option_id' => $priceOption->id]],
    ];

    $firstOrderId = $this->postJson($route, $payload)
        ->assertCreated()
        ->json('data.id');

    $this->postJson($route, $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'PICKUP_ALREADY_REQUESTED')
        ->assertJsonPath(
            'message',
            'Penjemputan untuk pesanan LDR-20260915-00000001 masih diproses.',
        );

    Order::query()->findOrFail($firstOrderId)->update(['status' => OrderStatus::Received]);

    $this->postJson($route, $payload)->assertCreated();
    $this->assertDatabaseCount('orders', 2);
});

it('keeps pickup address snapshots after the customer changes and removes the saved address', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'label' => 'Rumah Lama',
        'recipient_name' => 'Budi Lama',
        'whatsapp_phone' => '6281234567890',
        'address' => 'Jl. Lama No. 1',
        'notes' => 'Pagar biru',
    ]);
    Sanctum::actingAs($customer);

    $orderId = $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_address_id' => $address->id,
        'items' => [['price_option_id' => $priceOption->id]],
    ])->assertCreated()->json('data.id');

    $address->update([
        'label' => 'Rumah Baru',
        'address' => 'Jl. Baru No. 2',
    ]);
    $address->delete();

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'customer_address_id' => null,
        'pickup_label_snapshot' => 'Rumah Lama',
        'pickup_recipient_name_snapshot' => 'Budi Lama',
        'pickup_whatsapp_phone_snapshot' => '6281234567890',
        'pickup_address_snapshot' => 'Jl. Lama No. 1',
        'pickup_notes_snapshot' => 'Pagar biru',
    ]);
});

it('allows branch staff to create multiple orders for the same customer', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $customer->customerOrganizations()->attach($organization);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    Sanctum::actingAs($branchAdmin);
    $route = route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]);
    $payload = [
        'customer_id' => $customer->id,
        'items' => [['price_option_id' => $priceOption->id, 'weight_grams' => 1000]],
    ];

    $this->postJson($route, $payload)->assertCreated();
    $this->postJson($route, $payload)->assertCreated();

    $this->assertDatabaseCount('orders', 2);
});
