<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('returns customer context with active branches across organizations', function (): void {
    $organization = Organization::factory()->create(['slug' => 'jakarta-laundry']);
    $branch = Branch::factory()->for($organization)->create([
        'name' => 'Kemang',
        'qris_enabled' => true,
    ]);
    $otherOrganization = Organization::factory()->create(['slug' => 'bandung-laundry']);
    $otherBranch = Branch::factory()->for($otherOrganization)->create(['name' => 'Dago']);
    $inactiveOrganization = Organization::factory()->create(['is_active' => false]);
    Branch::factory()->for($inactiveOrganization)->create(['name' => 'Cabang Nonaktif']);
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.context'))
        ->assertOk()
        ->assertJsonPath('data.role', RoleName::Customer->value)
        ->assertJsonCount(2, 'data.branches')
        ->assertJsonFragment([
            'id' => $branch->id,
            'name' => 'Kemang',
            'slug' => 'jakarta-laundry',
        ])
        ->assertJsonFragment([
            'id' => $otherBranch->id,
            'name' => 'Dago',
            'slug' => 'bandung-laundry',
        ])
        ->assertJsonMissing(['name' => 'Cabang Nonaktif']);
});

it('does not restrict customer branches to their account organization', function (): void {
    Organization::factory()->create([
        'name' => 'Alpha Laundry',
        'slug' => 'alpha-laundry',
    ]);
    $operationalOrganization = Organization::factory()->create([
        'name' => 'Zulu Laundry',
        'slug' => 'zulu-laundry',
    ]);
    $branch = Branch::factory()->for($operationalOrganization)->create();
    $customer = User::factory()->forOrganization()->create();
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.context'))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $branch->id,
            'slug' => $operationalOrganization->slug,
        ]);
});

it('accepts browser session authentication for mobile api requests', function (): void {
    $organization = Organization::factory()->create(['slug' => 'session-laundry']);
    Branch::factory()->for($organization)->create();
    $customer = User::factory()->withCustomerPin()->create([
        'organization_id' => null,
        'email' => 'session-customer@example.test',
        'whatsapp_phone' => '6281234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $this->post(route('customer-login.store'), [
        'whatsapp_phone' => '081234567890',
        'pin' => '123456',
    ])->assertRedirect(route('mobile.app', absolute: false));

    $this->withHeader('Origin', config('app.url'))
        ->getJson(route('api.v1.mobile.context'))
        ->assertOk()
        ->assertJsonPath('data.role', RoleName::Customer->value)
        ->assertJsonPath('data.branches.0.organization.id', $organization->id);
});

it('locks branch admin context to their tenant and assigned branches', function (): void {
    $organization = Organization::factory()->create();
    $assignedBranch = Branch::factory()->for($organization)->create();
    Branch::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($assignedBranch);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.mobile.context', ['organization' => $otherOrganization->slug]))
        ->assertOk()
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.branches.0.id', $assignedBranch->id);
});

it('filters customer orders to the authenticated customer', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    $ownOrder = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
        'status' => OrderStatus::Washing,
    ]);
    Order::factory()->for($organization)->for($branch)->create();
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.organizations.orders.index', $organization))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownOrder->id);
});

it('returns customer orders across organizations without exposing an organization choice', function (): void {
    $firstOrganization = Organization::factory()->create(['slug' => 'jakarta-laundry']);
    $secondOrganization = Organization::factory()->create(['slug' => 'bandung-laundry']);
    $firstBranch = Branch::factory()->for($firstOrganization)->create();
    $secondBranch = Branch::factory()->for($secondOrganization)->create();
    $customer = User::factory()->for($firstOrganization)->create();
    $customer->assignRole(RoleName::Customer->value);
    $firstOrder = Order::factory()->for($firstOrganization)->for($firstBranch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
    ]);
    $secondOrder = Order::factory()->for($secondOrganization)->for($secondBranch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
    ]);
    Order::factory()->for($secondOrganization)->for($secondBranch)->create();
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.mobile.orders.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'id' => $firstOrder->id,
            'slug' => 'jakarta-laundry',
        ])
        ->assertJsonFragment([
            'id' => $secondOrder->id,
            'slug' => 'bandung-laundry',
        ]);
});

it('searches customer orders by order number branch and service without leaking other customers', function (): void {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $numberBranch = Branch::factory()->for($firstOrganization)->create();
    $namedBranch = Branch::factory()->for($secondOrganization)->create([
        'name' => 'Dago Express',
        'code' => 'DGO-22',
    ]);
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    $otherCustomer = User::factory()->create(['organization_id' => null]);
    $otherCustomer->assignRole(RoleName::Customer->value);
    $numberOrder = Order::factory()->for($firstOrganization)->for($numberBranch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
        'order_number' => 'LDR-MOBILE-SEARCH-9001',
    ]);
    $branchOrder = Order::factory()->for($secondOrganization)->for($namedBranch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
    ]);
    $serviceOrder = Order::factory()->for($firstOrganization)->for($numberBranch)->create([
        'customer_id' => $customer->id,
        'created_by_id' => $customer->id,
    ]);
    OrderItem::factory()->for($serviceOrder)->create([
        'service_name_snapshot' => 'Cuci Sepatu Premium',
        'service_code_snapshot' => 'CSP-77',
    ]);
    Order::factory()->for($firstOrganization)->for($numberBranch)->create([
        'customer_id' => $otherCustomer->id,
        'created_by_id' => $otherCustomer->id,
        'order_number' => 'LDR-LEAK-ONLY-0001',
    ]);
    Sanctum::actingAs($customer);
    $route = route('api.v1.mobile.orders.index');

    foreach ([
        'SEARCH-9001' => $numberOrder->id,
        'Dago Express' => $branchOrder->id,
        'DGO-22' => $branchOrder->id,
        'Sepatu Premium' => $serviceOrder->id,
        'CSP-77' => $serviceOrder->id,
    ] as $search => $expectedOrderId) {
        $this->getJson($route.'?'.http_build_query(['search' => $search]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expectedOrderId);
    }

    $this->getJson($route.'?search=LEAK-ONLY')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('applies inclusive and independent customer order date boundaries', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);

    $orders = collect([
        'before' => '2026-09-09 23:59:59',
        'start' => '2026-09-10 00:00:00',
        'end' => '2026-09-12 23:59:59',
        'after' => '2026-09-13 00:00:00',
    ])->map(fn (string $createdAt): Order => Order::factory()
        ->for($organization)
        ->for($branch)
        ->create([
            'customer_id' => $customer->id,
            'created_by_id' => $customer->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]));
    Sanctum::actingAs($customer);
    $route = route('api.v1.mobile.orders.index');

    $boundedResponse = $this->getJson($route.'?date_from=2026-09-10&date_to=2026-09-12')
        ->assertOk()
        ->assertJsonCount(2, 'data');
    expect($boundedResponse->json('data.*.id'))->toEqualCanonicalizing([
        $orders['start']->id,
        $orders['end']->id,
    ]);

    $fromResponse = $this->getJson($route.'?date_from=2026-09-10')
        ->assertOk()
        ->assertJsonCount(3, 'data');
    expect($fromResponse->json('data.*.id'))->toEqualCanonicalizing([
        $orders['start']->id,
        $orders['end']->id,
        $orders['after']->id,
    ]);

    $toResponse = $this->getJson($route.'?date_to=2026-09-12')
        ->assertOk()
        ->assertJsonCount(3, 'data');
    expect($toResponse->json('data.*.id'))->toEqualCanonicalizing([
        $orders['before']->id,
        $orders['start']->id,
        $orders['end']->id,
    ]);
});

it('rejects malformed and reversed customer order dates', function (): void {
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);
    $route = route('api.v1.mobile.orders.index');

    foreach ([
        ['date_from=2026-02-30', 'date_from'],
        ['date_to=15-09-2026', 'date_to'],
        ['date_from=2026-09-12&date_to=2026-09-10', 'date_to'],
    ] as [$query, $field]) {
        $this->getJson($route.'?'.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }
});

it('filters branch admin orders to assigned branches', function (): void {
    $organization = Organization::factory()->create();
    $assignedBranch = Branch::factory()->for($organization)->create();
    $unassignedBranch = Branch::factory()->for($organization)->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($assignedBranch);
    $visibleOrder = Order::factory()->for($organization)->for($assignedBranch)->create();
    Order::factory()->for($organization)->for($unassignedBranch)->create();
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.organizations.orders.index', $organization))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visibleOrder->id);
});

it('searches assigned branch orders by number customer name and normalized phone', function (): void {
    $organization = Organization::factory()->create();
    $assignedBranch = Branch::factory()->for($organization)->create();
    $unassignedBranch = Branch::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($assignedBranch);
    $visibleOrder = Order::factory()->for($organization)->for($assignedBranch)->create([
        'order_number' => 'LDR-SEARCH-0001',
        'customer_name_snapshot' => 'Ratna Kusuma',
        'customer_whatsapp_phone_snapshot' => '6281234567890',
    ]);
    Order::factory()->for($organization)->for($assignedBranch)->create([
        'order_number' => 'LDR-OTHER-0002',
        'customer_name_snapshot' => 'Pelanggan Lain',
        'customer_whatsapp_phone_snapshot' => '6287777777777',
    ]);
    Order::factory()->for($organization)->for($unassignedBranch)->create([
        'order_number' => 'LDR-SEARCH-HIDDEN',
        'customer_name_snapshot' => 'Ratna Kusuma',
        'customer_whatsapp_phone_snapshot' => '6281234567890',
    ]);
    Order::factory()->for($otherOrganization)->for($otherBranch)->create([
        'order_number' => 'LDR-SEARCH-TENANT',
        'customer_name_snapshot' => 'Ratna Kusuma',
        'customer_whatsapp_phone_snapshot' => '6281234567890',
    ]);
    Sanctum::actingAs($branchAdmin);
    $route = route('api.v1.organizations.orders.index', $organization);

    foreach (['SEARCH-0001', 'Ratna', '0812-3456-7890'] as $search) {
        $this->getJson($route.'?search='.urlencode($search))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleOrder->id);
    }
});

it('denies cross-tenant and unsupported role order lists', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.organizations.orders.index', $otherOrganization))
        ->assertForbidden();

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($owner);

    $this->getJson(route('api.v1.organizations.orders.index', $organization))
        ->assertForbidden();
});

it('returns only customers linked through the organization membership pivot', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    $linkedCustomer = User::factory()->create([
        'organization_id' => null,
        'name' => 'Linked Customer',
    ]);
    $linkedCustomer->assignRole(RoleName::Customer->value);
    $linkedCustomer->customerOrganizations()->attach($organization);
    $mixedRoleCustomer = User::factory()->create([
        'organization_id' => null,
        'name' => 'Mixed Role Customer',
    ]);
    $mixedRoleCustomer->assignRole([RoleName::Customer->value, RoleName::Owner->value]);
    $mixedRoleCustomer->customerOrganizations()->attach($organization);
    $legacyTenantCustomer = User::factory()->for($organization)->create([
        'name' => 'Legacy Tenant Customer',
    ]);
    $legacyTenantCustomer->assignRole(RoleName::Customer->value);
    $orderOnlyCustomer = User::factory()->create([
        'organization_id' => null,
        'name' => 'Order Only Customer',
    ]);
    $orderOnlyCustomer->assignRole(RoleName::Customer->value);
    Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $orderOnlyCustomer->id,
        'created_by_id' => $orderOnlyCustomer->id,
    ]);
    $otherCustomer = User::factory()->for($otherOrganization)->create();
    $otherCustomer->assignRole(RoleName::Customer->value);
    $otherCustomer->customerOrganizations()->attach($otherOrganization);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.organizations.customers.index', $organization))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['id' => $linkedCustomer->id, 'name' => 'Linked Customer'])
        ->assertJsonMissing(['id' => $mixedRoleCustomer->id])
        ->assertJsonMissing(['id' => $legacyTenantCustomer->id])
        ->assertJsonMissing(['id' => $orderOnlyCustomer->id])
        ->assertJsonMissing(['id' => $otherCustomer->id]);
});

it('denies customer access to the staff customer directory', function (): void {
    $organization = Organization::factory()->create();
    $customer = User::factory()->create(['organization_id' => null]);
    $customer->assignRole(RoleName::Customer->value);
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.organizations.customers.index', $organization))
        ->assertForbidden();
});
