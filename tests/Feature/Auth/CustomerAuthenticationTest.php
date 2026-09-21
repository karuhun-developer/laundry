<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('authenticates a customer with varied WhatsApp number formats and PIN', function (string $inputPhone): void {
    $customer = User::factory()->withCustomerPin()->create([
        'email' => null,
        'password' => null,
        'whatsapp_phone' => '6281234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->post(route('customer-login.store'), [
        'whatsapp_phone' => $inputPhone,
        'pin' => '123456',
    ]);

    $this->assertAuthenticatedAs($customer);
    $response
        ->assertRedirect(route('mobile.app', absolute: false))
        ->assertSessionHas('customer_session_version', 1);
})->with([
    'local format with spaces and dash' => '0812 3456-7890',
    'local format clean' => '081234567890',
    'international format' => '6281234567890',
    'international format with plus' => '+6281234567890',
    'shorthand without leading zero' => '81234567890',
    'double prefix 620' => '62081234567890',
]);

it('authenticates a customer with a shorter valid phone number such as test numbers', function (): void {
    $customer = User::factory()->withCustomerPin()->create([
        'email' => null,
        'password' => null,
        'whatsapp_phone' => '628123123',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->post(route('customer-login.store'), [
        'whatsapp_phone' => '08123123',
        'pin' => '123456',
    ]);

    $this->assertAuthenticatedAs($customer);
    $response->assertRedirect(route('mobile.app', absolute: false));
});

it('authenticates when customer was stored with local format in database', function (): void {
    $customer = User::factory()->withCustomerPin()->create([
        'email' => null,
        'password' => null,
        'whatsapp_phone' => '081234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->post(route('customer-login.store'), [
        'whatsapp_phone' => '081234567890',
        'pin' => '123456',
    ]);

    $this->assertAuthenticatedAs($customer);
    $response->assertRedirect(route('mobile.app', absolute: false));
});

it('returns the same error for an unknown customer and an invalid PIN', function (array $credentials): void {
    $customer = User::factory()->withCustomerPin()->create([
        'email' => null,
        'password' => null,
        'whatsapp_phone' => '6281234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->postJson(route('customer-login.store'), $credentials);

    $this->assertGuest();
    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['whatsapp_phone'])
        ->assertJsonPath('errors.whatsapp_phone.0', 'Nomor WhatsApp atau PIN tidak valid.');
})->with([
    'unknown customer' => [[
        'whatsapp_phone' => '081299999999',
        'pin' => '123456',
    ]],
    'invalid PIN' => [[
        'whatsapp_phone' => '081234567890',
        'pin' => '654321',
    ]],
]);

it('rejects malformed customer credentials before authentication', function (array $credentials, string $field): void {
    $response = $this->postJson(route('customer-login.store'), $credentials);

    $this->assertGuest();
    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'malformed WhatsApp number' => [[
        'whatsapp_phone' => 'not-a-phone',
        'pin' => '123456',
    ], 'whatsapp_phone'],
    'malformed PIN' => [[
        'whatsapp_phone' => '081234567890',
        'pin' => '12345x',
    ], 'pin'],
]);

it('prevents staff accounts from using the customer login endpoint', function (): void {
    $staff = User::factory()->withCustomerPin()->create([
        'whatsapp_phone' => '6281234567890',
    ]);
    $staff->assignRole(RoleName::BranchAdmin->value);

    $response = $this->postJson(route('customer-login.store'), [
        'whatsapp_phone' => '081234567890',
        'pin' => '123456',
    ]);

    $this->assertGuest();
    $response
        ->assertUnprocessable()
        ->assertJsonPath('errors.whatsapp_phone.0', 'Nomor WhatsApp atau PIN tidak valid.');
});

it('prevents customer accounts from using the staff password login', function (): void {
    $customer = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'password',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->postJson(route('login.store'), [
        'email' => 'customer@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('auth.failed'));
});

it('rate limits repeated customer login failures', function (): void {
    $customer = User::factory()->withCustomerPin()->create([
        'email' => null,
        'password' => null,
        'whatsapp_phone' => '6281234567890',
    ]);
    $customer->assignRole(RoleName::Customer->value);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('customer-login.store'), [
            'whatsapp_phone' => '081234567890',
            'pin' => '654321',
        ])->assertUnprocessable();
    }

    $this->postJson(route('customer-login.store'), [
        'whatsapp_phone' => '081234567890',
        'pin' => '654321',
    ])
        ->assertTooManyRequests()
        ->assertJsonValidationErrors(['whatsapp_phone'])
        ->assertJsonPath(
            'errors.whatsapp_phone.0',
            'Too many login attempts. Please try again in 60 seconds.',
        );
});

it('redirects staff to the destination for their role after password login', function (RoleName $role, string $routeName): void {
    $staff = User::factory()->create();
    $staff->assignRole($role->value);

    $response = $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($staff);
    $response->assertRedirect(route($routeName, absolute: false));
})->with([
    'branch admin' => [RoleName::BranchAdmin, 'mobile.app'],
    'owner' => [RoleName::Owner, 'cms.dashboard'],
    'superadmin' => [RoleName::Superadmin, 'cms.dashboard'],
]);

it('allows passkey login only for staff accounts', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);
    $customerPasskey = (new Passkey)->setRelation('user', $customer);

    $staff = User::factory()->create();
    $staff->assignRole(RoleName::Owner->value);
    $staffPasskey = (new Passkey)->setRelation('user', $staff);
    $request = Request::create('/passkeys/login', 'POST');

    expect(Passkeys::allowsLogin($request, $customerPasskey))->toBeFalse();
    expect(Passkeys::allowsLogin($request, $staffPasskey))->toBeTrue();
});
