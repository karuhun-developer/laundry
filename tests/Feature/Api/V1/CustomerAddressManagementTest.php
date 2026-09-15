<?php

use App\Enums\RoleName;
use App\Models\CustomerAddress;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function addressCustomer(array $attributes = []): User
{
    $customer = User::factory()->withCustomerPin()->create(array_merge([
        'organization_id' => null,
        'name' => 'Pelanggan Alamat',
        'whatsapp_phone' => '6281234567890',
    ], $attributes));
    $customer->assignRole(RoleName::Customer->value);

    return $customer;
}

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Rumah',
        'recipient_name' => 'Budi Santoso',
        'whatsapp_phone' => '0812-3456-7890',
        'address' => 'Jl. Melati No. 10, Jakarta Selatan',
        'notes' => 'Pagar warna biru',
        'latitude' => -6.261493,
        'longitude' => 106.8106,
        'is_default' => false,
    ], $overrides);
}

it('creates the first customer address as default and normalizes its WhatsApp number', function (): void {
    $customer = addressCustomer();
    Sanctum::actingAs($customer);

    $response = $this->postJson(route('api.v1.mobile.account.addresses.store'), addressPayload());

    $response->assertCreated()
        ->assertJsonPath('data.label', 'Rumah')
        ->assertJsonPath('data.whatsapp_phone', '6281234567890')
        ->assertJsonPath('data.is_default', true);
    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'whatsapp_phone' => '6281234567890',
        'is_default' => true,
    ]);
});

it('updates selects lists and deletes addresses while preserving one default', function (): void {
    $customer = addressCustomer();
    $home = CustomerAddress::factory()->default()->for($customer, 'customer')->create([
        'label' => 'Rumah',
    ]);
    $office = CustomerAddress::factory()->for($customer, 'customer')->create([
        'label' => 'Kantor',
    ]);
    Sanctum::actingAs($customer);

    $this->putJson(route('api.v1.mobile.account.addresses.default', $office))
        ->assertOk()
        ->assertJsonPath('data.id', $office->id)
        ->assertJsonPath('data.is_default', true);

    expect($home->refresh()->is_default)->toBeFalse();

    $this->patchJson(route('api.v1.mobile.account.addresses.update', $office), addressPayload([
        'label' => 'Kantor Baru',
        'is_default' => true,
    ]))->assertOk()
        ->assertJsonPath('data.label', 'Kantor Baru');

    $this->getJson(route('api.v1.mobile.account.addresses.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $office->id);

    $this->deleteJson(route('api.v1.mobile.account.addresses.destroy', $office))
        ->assertNoContent();

    expect($home->refresh()->is_default)->toBeTrue();
    $this->assertDatabaseMissing('customer_addresses', ['id' => $office->id]);
});

it('hides another customer address and rejects staff access', function (): void {
    $customer = addressCustomer();
    $otherCustomer = addressCustomer(['whatsapp_phone' => '6289876543210']);
    $otherAddress = CustomerAddress::factory()->for($otherCustomer, 'customer')->create();
    Sanctum::actingAs($customer);

    $this->patchJson(
        route('api.v1.mobile.account.addresses.update', $otherAddress),
        addressPayload(),
    )->assertNotFound();
    $this->putJson(route('api.v1.mobile.account.addresses.default', $otherAddress))
        ->assertNotFound();
    $this->deleteJson(route('api.v1.mobile.account.addresses.destroy', $otherAddress))
        ->assertNotFound();

    $organization = Organization::factory()->create();
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($branchAdmin);

    $this->getJson(route('api.v1.mobile.account.addresses.index'))->assertForbidden();
    $this->postJson(route('api.v1.mobile.account.addresses.store'), addressPayload())->assertForbidden();
});

it('rejects incomplete and unexpected address fields', function (): void {
    Sanctum::actingAs(addressCustomer());

    $this->postJson(route('api.v1.mobile.account.addresses.store'), [
        'label' => 'Rumah',
        'unexpected' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'recipient_name',
            'whatsapp_phone',
            'address',
            'latitude',
            'longitude',
            'unexpected',
        ]);
});

it('requires authentication for every address endpoint', function (string $method, string $routeName): void {
    $address = CustomerAddress::factory()->create();
    $parameters = str_contains($routeName, 'addresses.index') || str_contains($routeName, 'addresses.store')
        ? []
        : [$address];

    $this->json($method, route($routeName, $parameters), addressPayload())
        ->assertUnauthorized();
})->with([
    ['GET', 'api.v1.mobile.account.addresses.index'],
    ['POST', 'api.v1.mobile.account.addresses.store'],
    ['PATCH', 'api.v1.mobile.account.addresses.update'],
    ['DELETE', 'api.v1.mobile.account.addresses.destroy'],
    ['PUT', 'api.v1.mobile.account.addresses.default'],
]);
