<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusEvent;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-14 12:00:00');
    $this->seed(RolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('builds tenant isolated owner metrics and branch comparisons', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Alpha']);
    $otherOrganization = Organization::factory()->create(['name' => 'Laundry Lain']);
    $city = City::factory()->create(['name' => 'Jakarta Selatan']);
    City::factory()->create(['name' => 'Bandung']);
    $branchAlpha = Branch::factory()->create([
        'organization_id' => $organization->id,
        'city_id' => $city->id,
        'name' => 'Cabang Alpha',
        'code' => 'ALPHA',
    ]);
    $branchBeta = Branch::factory()->create([
        'organization_id' => $organization->id,
        'city_id' => $city->id,
        'name' => 'Cabang Beta',
        'code' => 'BETA',
    ]);
    $otherBranch = Branch::factory()->create([
        'organization_id' => $otherOrganization->id,
        'city_id' => $city->id,
        'name' => 'Cabang Tenant Lain',
    ]);
    $service = Service::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Cuci reguler',
    ]);
    $completedOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branchAlpha->id,
        'status' => OrderStatus::Completed,
        'created_at' => '2026-09-10 08:00:00',
        'updated_at' => '2026-09-10 10:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $completedOrder->id,
        'service_id' => $service->id,
        'price_option_id' => null,
    ]);
    OrderStatusEvent::factory()->create([
        'order_id' => $completedOrder->id,
        'actor_id' => null,
        'status' => OrderStatus::Completed,
        'created_at' => '2026-09-10 10:00:00',
        'updated_at' => '2026-09-10 10:00:00',
    ]);
    Payment::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branchAlpha->id,
        'order_id' => $completedOrder->id,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 20000,
    ]);
    Payment::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branchAlpha->id,
        'order_id' => $completedOrder->id,
        'status' => PaymentStatus::Refunded,
        'amount_rupiah' => 5000,
    ]);

    $cancelledOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branchBeta->id,
        'status' => OrderStatus::Cancelled,
        'created_at' => '2026-09-11 09:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $cancelledOrder->id,
        'service_id' => $service->id,
        'price_option_id' => null,
    ]);

    $otherOrder = Order::factory()->create([
        'organization_id' => $otherOrganization->id,
        'branch_id' => $otherBranch->id,
        'status' => OrderStatus::Completed,
        'created_at' => '2026-09-10 08:00:00',
    ]);
    Payment::factory()->create([
        'organization_id' => $otherOrganization->id,
        'branch_id' => $otherBranch->id,
        'order_id' => $otherOrder->id,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 99000,
    ]);

    $owner = User::factory()->create(['organization_id' => $organization->id]);
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('dashboard', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('filters.organization_id', $organization->id)
            ->where('summary.paid_revenue_rupiah', 20000)
            ->where('summary.order_count', 2)
            ->where('summary.average_turnaround_minutes', 120)
            ->where('summary.cancelled_order_count', 1)
            ->where('summary.cancellation_rate', 50)
            ->has('branches', 2)
            ->where('branches.0.branch_name', 'Cabang Alpha')
            ->where('branches.0.paid_revenue_rupiah', 20000)
            ->where('branches.0.average_turnaround_minutes', 120)
            ->where('branches.1.branch_name', 'Cabang Beta')
            ->where('branches.1.cancelled_order_count', 1)
            ->has('options.cities', 2)
            ->where('options.cities.0.name', 'Bandung')
            ->where('options.organizations', []));
});

it('lets superadmins combine organization service and status filters', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $city = City::factory()->create();
    $branch = Branch::factory()->create([
        'organization_id' => $organization->id,
        'city_id' => $city->id,
    ]);
    $otherBranch = Branch::factory()->create([
        'organization_id' => $otherOrganization->id,
        'city_id' => $city->id,
    ]);
    $service = Service::factory()->create(['organization_id' => $organization->id]);
    $otherService = Service::factory()->create(['organization_id' => $organization->id]);
    $matchingOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'status' => OrderStatus::Completed,
        'created_at' => '2026-09-12 08:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $matchingOrder->id,
        'service_id' => $service->id,
        'price_option_id' => null,
    ]);
    $wrongServiceOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'status' => OrderStatus::Completed,
        'created_at' => '2026-09-12 09:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $wrongServiceOrder->id,
        'service_id' => $otherService->id,
        'price_option_id' => null,
    ]);
    Order::factory()->create([
        'organization_id' => $otherOrganization->id,
        'branch_id' => $otherBranch->id,
        'status' => OrderStatus::Completed,
        'created_at' => '2026-09-12 10:00:00',
    ]);

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)
        ->get(route('dashboard', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'organization_id' => $organization->id,
            'service_id' => $service->id,
            'status' => OrderStatus::Completed->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.order_count', 1)
            ->where('filters.organization_id', $organization->id)
            ->where('filters.service_id', $service->id)
            ->where('filters.status', OrderStatus::Completed->value)
            ->has('options.organizations', 2));
});

it('rejects owner filters that point to another tenant', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherBranch = Branch::factory()->create(['organization_id' => $otherOrganization->id]);
    $otherService = Service::factory()->create(['organization_id' => $otherOrganization->id]);
    $owner = User::factory()->create(['organization_id' => $organization->id]);
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->get(route('dashboard', [
            'branch_id' => $otherBranch->id,
            'service_id' => $otherService->id,
        ]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['branch_id', 'service_id']);
});

it('exports the same tenant isolated report as csv', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry CSV']);
    $otherOrganization = Organization::factory()->create(['name' => 'Tenant Rahasia']);
    $city = City::factory()->create(['name' => 'Bandung']);
    $branch = Branch::factory()->create([
        'organization_id' => $organization->id,
        'city_id' => $city->id,
        'name' => 'Cabang Dago',
        'code' => 'DAGO',
    ]);
    $otherBranch = Branch::factory()->create([
        'organization_id' => $otherOrganization->id,
        'city_id' => $city->id,
    ]);
    $order = Order::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-09-13 08:00:00',
    ]);
    Payment::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'order_id' => $order->id,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 17500,
    ]);
    $otherOrder = Order::factory()->create([
        'organization_id' => $otherOrganization->id,
        'branch_id' => $otherBranch->id,
        'created_at' => '2026-09-13 08:00:00',
    ]);
    Payment::factory()->create([
        'organization_id' => $otherOrganization->id,
        'branch_id' => $otherBranch->id,
        'order_id' => $otherOrder->id,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 90000,
    ]);
    $owner = User::factory()->create(['organization_id' => $organization->id]);
    $owner->assignRole(RoleName::Owner->value);

    $response = $this->actingAs($owner)->get(route('dashboard.export', [
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]));

    $response
        ->assertOk()
        ->assertDownload('laporan-operasional-2026-09-01-2026-09-30.csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Laundry CSV')
        ->toContain('Cabang Dago')
        ->toContain('17500')
        ->not->toContain('Tenant Rahasia')
        ->not->toContain('90000');
});
