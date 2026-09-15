<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createWhatsAppSuggestionContext(OrderStatus $status = OrderStatus::ReadyForPickup): array
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Dago']);
    $customer = User::factory()->create([
        'name' => 'Budi Santoso',
        'whatsapp_phone' => '6281234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    $branchAdmin->branches()->attach($branch);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_id' => $customer->id,
        'customer_name_snapshot' => $customer->name,
        'customer_whatsapp_phone_snapshot' => '0812-3456-7890',
        'order_number' => 'LDR-20260913-00000001',
        'status' => $status,
    ]);

    return [$organization, $branch, $order, $branchAdmin, $customer];
}

function whatsAppSuggestionRoute(
    Organization $organization,
    Branch $branch,
    Order $order,
): string {
    return route('api.v1.organizations.branches.orders.whatsapp-suggestion.show', [
        'organization' => $organization,
        'branch' => $branch,
        'order' => $order,
    ]);
}

it('prefers a branch template and returns a normalized RFC3986 wa.me suggestion', function (): void {
    [$organization, $branch, $order, $branchAdmin] = createWhatsAppSuggestionContext();
    MessageTemplate::factory()->for($organization)->create([
        'order_status' => OrderStatus::ReadyForPickup,
        'body' => 'Organisasi {customer_name}',
    ]);
    $branchTemplate = MessageTemplate::factory()->forBranch($branch)->create([
        'order_status' => OrderStatus::ReadyForPickup,
        'body' => 'Halo {name}, pesanan {order_number} {status} di {branch_name}. {tracking_url}',
    ]);
    Sanctum::actingAs($branchAdmin);

    $response = $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.phone', '6281234567890')
        ->assertJsonPath('data.template_id', $branchTemplate->id)
        ->assertJsonPath('data.unavailable_reason', null);

    $message = $response->json('data.message');
    $url = $response->json('data.url');
    $trackingUrl = URL::signedRoute('orders.public.show', [
        'order' => $order->order_number,
    ]);

    expect($message)->toContain('Halo Budi Santoso')
        ->toContain('LDR-20260913-00000001')
        ->toContain('Siap diambil')
        ->toContain('Cabang Dago')
        ->toContain($trackingUrl)
        ->not->toContain('/app?order=')
        ->not->toContain('/pesanan/'.$order->id.'?')
        ->not->toContain('expires=')
        ->and($url)->toStartWith('https://wa.me/6281234567890?text=')
        ->toContain('%20')
        ->not->toContain('+');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['text'] ?? null)->toBe($message);
});

it('falls back to the active organization template', function (): void {
    [$organization, $branch, $order, $branchAdmin] = createWhatsAppSuggestionContext(
        OrderStatus::Washing,
    );
    $template = MessageTemplate::factory()->for($organization)->create([
        'order_status' => OrderStatus::Washing,
        'body' => 'Pesanan {order_number} sedang {status}.',
    ]);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.template_id', $template->id)
        ->assertJsonPath('data.message', 'Pesanan LDR-20260913-00000001 sedang Dicuci.');
});

it('renders the confirmed weight and total in a weighed-order suggestion', function (): void {
    [$organization, $branch, $order, $branchAdmin] = createWhatsAppSuggestionContext(
        OrderStatus::Weighed,
    );
    $order->update([
        'items_subtotal_rupiah' => 42000,
        'delivery_fee_rupiah' => 6000,
        'total_rupiah' => 48000,
        'priced_at' => now(),
    ]);
    OrderItem::factory()->for($order)->create([
        'requested_weight_grams' => 3500,
        'chargeable_weight_grams' => 3500,
        'subtotal_rupiah' => 42000,
    ]);
    MessageTemplate::factory()->for($organization)->create([
        'order_status' => OrderStatus::Weighed,
        'body' => 'Berat {weight}. Total {total}.',
    ]);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))
        ->assertOk()
        ->assertJsonPath('data.message', 'Berat 3,5 kg. Total Rp48.000.');
});

it('returns an unavailable suggestion without sending when template or phone is missing', function (): void {
    [$organization, $branch, $order, $branchAdmin] = createWhatsAppSuggestionContext();
    Sanctum::actingAs($branchAdmin);

    $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.url', null)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Template WhatsApp aktif belum tersedia untuk status ini.',
        );

    MessageTemplate::factory()->for($organization)->create([
        'order_status' => $order->status,
    ]);
    $order->update(['customer_whatsapp_phone_snapshot' => null]);

    $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.url', null)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
        );
});

it('enforces authentication, staff authorization, and nested tenant bindings', function (): void {
    [$organization, $branch, $order, $branchAdmin, $customer] = createWhatsAppSuggestionContext();
    $route = whatsAppSuggestionRoute($organization, $branch, $order);

    $this->getJson($route)->assertUnauthorized();

    Sanctum::actingAs($customer);
    $this->getJson($route)->assertForbidden();

    $unassignedAdmin = User::factory()->for($organization)->create();
    $unassignedAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($unassignedAdmin);
    $this->getJson($route)->assertForbidden();

    $otherBranch = Branch::factory()->for($organization)->create();
    $branchAdmin->branches()->attach($otherBranch);
    Sanctum::actingAs($branchAdmin);
    $this->getJson(whatsAppSuggestionRoute($organization, $otherBranch, $order))
        ->assertNotFound();
});

it('allows owners in their tenant and superadmins across tenants', function (): void {
    [$organization, $branch, $order] = createWhatsAppSuggestionContext();
    MessageTemplate::factory()->for($organization)->create([
        'order_status' => $order->status,
    ]);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    Sanctum::actingAs($owner);

    $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))->assertOk();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);
    Sanctum::actingAs($superadmin);

    $this->getJson(whatsAppSuggestionRoute($organization, $branch, $order))->assertOk();
});
