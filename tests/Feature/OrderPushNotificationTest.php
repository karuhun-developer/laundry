<?php

use App\Enums\OrderStatus;
use App\Enums\PushNotificationEvent;
use App\Enums\RoleName;
use App\Jobs\SendOrderPushNotification;
use App\Models\Branch;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\PushNotificationDevice;
use App\Models\Service;
use App\Models\User;
use App\Services\FirebaseCloudMessaging;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config([
        'services.firebase.project_id' => null,
        'services.firebase.credentials' => null,
    ]);
    Cache::forget('firebase-cloud-messaging-access-token');
});

function pushNotificationOrderCatalog(): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'latitude' => 0,
        'longitude' => 0,
    ]);
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();

    return [$organization, $branch, $service, $priceOption];
}

function configureFirebaseCloudMessagingForTest(): void
{
    config([
        'services.firebase.project_id' => 'laundry-695f6',
        'services.firebase.credentials' => __FILE__,
    ]);
    Cache::put('firebase-cloud-messaging-access-token', 'fake-access-token');
}

it('queues a pickup notification for customer requests but not staff counter orders', function (): void {
    [$organization, $branch, , $priceOption] = pushNotificationOrderCatalog();
    $customer = User::factory()->create(['whatsapp_phone' => '6281234567890']);
    $customer->assignRole(RoleName::Customer->value);
    $address = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'latitude' => 0.01,
        'longitude' => 0.01,
    ]);
    Queue::fake();
    Sanctum::actingAs($customer);

    $orderId = $this->postJson(route('api.v1.organizations.branches.orders.store', [
        'organization' => $organization,
        'branch' => $branch,
    ]), [
        'customer_address_id' => $address->id,
        'items' => [['price_option_id' => $priceOption->id]],
    ])->assertCreated()->json('data.id');

    Queue::assertPushed(
        SendOrderPushNotification::class,
        fn (SendOrderPushNotification $job): bool => $job->orderId === $orderId
            && $job->event === PushNotificationEvent::PickupRequested,
    );

    Queue::fake();
    $customer->customerOrganizations()->syncWithoutDetaching([$organization->id]);
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
            'weight_grams' => 1000,
        ]],
    ])->assertCreated();

    Queue::assertNotPushed(SendOrderPushNotification::class);
});

it('queues pricing and status notifications for customer orders', function (): void {
    [$organization, $branch, $service, $priceOption] = pushNotificationOrderCatalog();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::PickupRequested,
        'priced_at' => null,
        'items_subtotal_rupiah' => 0,
        'total_rupiah' => 0,
    ]);
    OrderItem::factory()->for($order)->for($service)->for($priceOption)->create([
        'requested_weight_grams' => 0,
        'chargeable_weight_grams' => 0,
        'subtotal_rupiah' => 0,
    ]);
    Sanctum::actingAs($branchAdmin);
    Queue::fake();

    $this->postJson(route('api.v1.organizations.branches.orders.pricing.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]), [
        'price_option_id' => $priceOption->id,
        'weight_grams' => 1500,
    ])->assertOk();

    Queue::assertPushed(
        SendOrderPushNotification::class,
        fn (SendOrderPushNotification $job): bool => $job->orderId === $order->id
            && $job->event === PushNotificationEvent::PricingConfirmed,
    );

    Queue::fake();
    $this->postJson(route('api.v1.organizations.branches.orders.status-events.store', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]), ['status' => OrderStatus::Washing->value])->assertOk();

    Queue::assertPushed(
        SendOrderPushNotification::class,
        fn (SendOrderPushNotification $job): bool => $job->orderId === $order->id
            && $job->event === PushNotificationEvent::StatusUpdated,
    );
});

it('sends pickup notifications only to admins assigned to the requested branch', function (): void {
    configureFirebaseCloudMessagingForTest();
    Http::preventStrayRequests();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['name' => 'sent'], 200)]);
    $organization = Organization::factory()->create();
    $requestedBranch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $assignedAdmin = User::factory()->for($organization)->create();
    $assignedAdmin->assignRole(RoleName::BranchAdmin->value);
    $assignedAdmin->branches()->attach($requestedBranch);
    $otherAdmin = User::factory()->for($organization)->create();
    $otherAdmin->assignRole(RoleName::BranchAdmin->value);
    $otherAdmin->branches()->attach($otherBranch);
    $assignedToken = 'assigned-admin-token';
    PushNotificationDevice::factory()->for($assignedAdmin)->create([
        'token' => $assignedToken,
        'token_hash' => hash('sha256', $assignedToken),
    ]);
    $otherToken = 'other-admin-token';
    PushNotificationDevice::factory()->for($otherAdmin)->create([
        'token' => $otherToken,
        'token_hash' => hash('sha256', $otherToken),
    ]);
    $order = Order::factory()->for($organization)->for($requestedBranch)->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::PickupRequested,
    ]);

    (new SendOrderPushNotification(
        $order->id,
        PushNotificationEvent::PickupRequested,
    ))->handle(app(FirebaseCloudMessaging::class));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['message']['token'] === $assignedToken
        && $request['message']['data']['type'] === 'pickup_requested');
});

it('sends pricing and status notifications only to the order customer', function (): void {
    configureFirebaseCloudMessagingForTest();
    Http::preventStrayRequests();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['name' => 'sent'], 200)]);
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $admin = User::factory()->for($organization)->create();
    $admin->assignRole(RoleName::BranchAdmin->value);
    $admin->branches()->attach($branch);
    $customerToken = 'customer-device-token';
    PushNotificationDevice::factory()->for($customer)->create([
        'token' => $customerToken,
        'token_hash' => hash('sha256', $customerToken),
    ]);
    $adminToken = 'admin-device-token';
    PushNotificationDevice::factory()->for($admin)->create([
        'token' => $adminToken,
        'token_hash' => hash('sha256', $adminToken),
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);

    foreach ([PushNotificationEvent::PricingConfirmed, PushNotificationEvent::StatusUpdated] as $event) {
        (new SendOrderPushNotification($order->id, $event))
            ->handle(app(FirebaseCloudMessaging::class));
    }

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['message']['token'] === $customerToken);
});

it('removes an invalid Firebase token after an unregistered response', function (): void {
    configureFirebaseCloudMessagingForTest();
    Http::preventStrayRequests();
    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'error' => ['details' => [['errorCode' => 'UNREGISTERED']]],
        ], 404),
    ]);
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $token = 'invalid-customer-token';
    $device = PushNotificationDevice::factory()->for($customer)->create([
        'token' => $token,
        'token_hash' => hash('sha256', $token),
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);

    (new SendOrderPushNotification($order->id, PushNotificationEvent::StatusUpdated))
        ->handle(app(FirebaseCloudMessaging::class));

    $this->assertDatabaseMissing('push_notification_devices', ['id' => $device->id]);
});

it('does not call Firebase when server credentials are not configured', function (): void {
    Http::preventStrayRequests();
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    PushNotificationDevice::factory()->for($customer)->create();
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
    ]);

    (new SendOrderPushNotification($order->id, PushNotificationEvent::StatusUpdated))
        ->handle(app(FirebaseCloudMessaging::class));

    Http::assertNothingSent();
});
