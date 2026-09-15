<?php

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders signed order tracking publicly using the human order number', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Dago']);
    $order = Order::factory()->for($organization)->for($branch)->create([
        'order_number' => 'LDR-20260915-00000042',
        'status' => OrderStatus::Washing,
    ]);
    $order->statusEvents()->createMany([
        [
            'actor_name_snapshot' => 'Admin Cabang',
            'status' => OrderStatus::Received,
        ],
        [
            'actor_name_snapshot' => 'Admin Cabang',
            'status' => OrderStatus::Washing,
            'note' => 'Sedang diproses dengan hati-hati',
        ],
    ]);
    $url = URL::signedRoute('orders.public.show', [
        'order' => $order->order_number,
    ]);

    expect($url)->toContain('/pesanan/LDR-20260915-00000042?')
        ->not->toContain('/pesanan/'.$order->id.'?')
        ->not->toContain('expires=');

    $this->get($url)
        ->assertOk()
        ->assertSee('Status pesanan')
        ->assertSee('LDR-20260915-00000042')
        ->assertSee('Cabang Dago')
        ->assertSee('Dicuci')
        ->assertSee('Sedang diproses dengan hati-hati')
        ->assertSee('property="og:title" content="Status LDR-20260915-00000042 | LaundryHub"', false)
        ->assertSee('property="og:url" content="'.e($url).'"', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
});

it('rejects unsigned and tampered public order tracking URLs', function (): void {
    $order = Order::factory()->create(['order_number' => 'LDR-20260915-00000043']);

    $this->get(route('orders.public.show', ['order' => $order->order_number]))
        ->assertForbidden();

    $validUrl = URL::signedRoute('orders.public.show', [
        'order' => $order->order_number,
    ]);

    $this->get($validUrl.'&tampered=1')->assertForbidden();
});
