<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;

function createPublicReceiptOrder(array $orderAttributes = [], array $branchAttributes = []): Order
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create([
        'name' => 'Cabang Kemang',
        'address' => 'Jl. Kemang Raya No. 10',
        ...$branchAttributes,
    ]);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'customer_name_snapshot' => 'Budi',
        'items_subtotal_rupiah' => 24_000,
        'delivery_fee_rupiah' => 3_000,
        'total_rupiah' => 27_000,
        ...$orderAttributes,
    ]);
    OrderItem::factory()->for($order)->create([
        'service_name_snapshot' => 'Cuci Kering Lipat',
        'requested_weight_grams' => 2_000,
        'subtotal_rupiah' => 24_000,
    ]);
    Payment::factory()->for($organization)->for($branch)->for($order)->create([
        'method' => PaymentMethod::Cash,
        'status' => PaymentStatus::Paid,
        'amount_rupiah' => 20_000,
    ]);

    return $order;
}

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders a signed public receipt with WhatsApp preview metadata', function (): void {
    $order = createPublicReceiptOrder();
    $url = URL::temporarySignedRoute(
        'receipts.public.show',
        now()->addDays(7),
        ['order' => $order],
    );

    $response = $this->get($url);

    $response->assertOk()
        ->assertSee('Struk '.$order->order_number)
        ->assertSee('Cabang Kemang')
        ->assertSee('Budi')
        ->assertSee('Cuci Kering Lipat')
        ->assertSee('Rp27.000')
        ->assertSee('Sisa Rp7.000')
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('property="og:title" content="Struk '.$order->order_number.' | LaundryHub"', false)
        ->assertSee('property="og:url" content="'.e($url).'"', false)
        ->assertSee('property="og:image" content="'.asset('apple-touch-icon.png').'"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
});

it('escapes customer branch and address content in the public receipt', function (): void {
    $customerName = '<script>alert("customer")</script>';
    $branchName = '<b>Cabang Uji</b>';
    $branchAddress = '<img src=x onerror=alert(1)>';
    $order = createPublicReceiptOrder(
        ['customer_name_snapshot' => $customerName],
        ['name' => $branchName, 'address' => $branchAddress],
    );
    $url = URL::temporarySignedRoute(
        'receipts.public.show',
        now()->addDay(),
        ['order' => $order],
    );

    $response = $this->get($url);

    $response->assertOk()
        ->assertDontSee($customerName, false)
        ->assertDontSee($branchName, false)
        ->assertDontSee($branchAddress, false)
        ->assertSee(e($customerName), false)
        ->assertSee(e($branchName), false)
        ->assertSee(e($branchAddress), false);
});

it('rejects missing tampered and expired receipt signatures', function (): void {
    $order = createPublicReceiptOrder();

    $this->get(route('receipts.public.show', $order))->assertForbidden();

    $validUrl = URL::temporarySignedRoute(
        'receipts.public.show',
        now()->addMinute(),
        ['order' => $order],
    );
    $this->get($validUrl.'&tampered=1')->assertForbidden();

    $expiredUrl = URL::temporarySignedRoute(
        'receipts.public.show',
        now()->subMinute(),
        ['order' => $order],
    );
    $this->get($expiredUrl)->assertForbidden();
});
