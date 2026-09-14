<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when the customer is unauthenticated', function (): void {
    $this->putJson(route('api.v1.mobile.account.pin.update'), [
        'current_pin' => '123456',
        'pin' => '654321',
        'pin_confirmation' => '654321',
    ])->assertUnauthorized();
});

it('returns 403 when a staff account attempts to update a customer PIN', function (): void {
    $staff = User::factory()->withCustomerPin()->create();
    $staff->assignRole(RoleName::BranchAdmin->value);
    Sanctum::actingAs($staff);

    $this->putJson(route('api.v1.mobile.account.pin.update'), [
        'current_pin' => '123456',
        'pin' => '654321',
        'pin_confirmation' => '654321',
    ])->assertForbidden();
});

it('returns a generic validation error when the current PIN is invalid', function (): void {
    $customer = User::factory()->withCustomerPin()->create();
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->actingAs($customer)
        ->withHeader('Origin', (string) config('app.url'))
        ->withSession(['customer_session_version' => 1])
        ->putJson(route('api.v1.mobile.account.pin.update'), [
            'current_pin' => '999999',
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_pin'])
        ->assertJsonPath('errors.current_pin.0', 'PIN saat ini tidak valid.');
    expect(Hash::check('123456', $customer->refresh()->pin_hash))->toBeTrue();
});

it('validates the replacement PIN', function (array $payload, string $errorField): void {
    $customer = User::factory()->withCustomerPin()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->actingAs($customer)
        ->withHeader('Origin', (string) config('app.url'))
        ->withSession(['customer_session_version' => 1])
        ->putJson(route('api.v1.mobile.account.pin.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorField]);

    expect(Hash::check('123456', $customer->refresh()->pin_hash))->toBeTrue();
})->with([
    'confirmation does not match' => [[
        'current_pin' => '123456',
        'pin' => '654321',
        'pin_confirmation' => '654320',
    ], 'pin'],
    'replacement matches current PIN' => [[
        'current_pin' => '123456',
        'pin' => '123456',
        'pin_confirmation' => '123456',
    ], 'pin'],
]);

it('updates the PIN and advances the current customer session version', function (): void {
    $customer = User::factory()->withCustomerPin()->create([
        'must_change_pin' => true,
        'pin_expires_at' => now()->addDay(),
        'session_version' => 4,
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->actingAs($customer)
        ->withHeader('Origin', (string) config('app.url'))
        ->withSession(['customer_session_version' => 4])
        ->putJson(route('api.v1.mobile.account.pin.update'), [
            'current_pin' => '123456',
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ]);

    $response
        ->assertOk()
        ->assertExactJson([
            'message' => 'PIN berhasil diperbarui.',
            'data' => [
                'must_change_pin' => false,
                'pin_expires_at' => null,
            ],
        ])
        ->assertSessionHas('customer_session_version', 5);
    $this->assertAuthenticatedAs($customer);

    $customer->refresh();

    expect(Hash::check('654321', $customer->pin_hash))->toBeTrue();
    expect(Hash::check('123456', $customer->pin_hash))->toBeFalse();
    expect($customer->must_change_pin)->toBeFalse();
    expect($customer->pin_expires_at)->toBeNull();
    expect($customer->session_version)->toBe(5);
});

it('removes other database sessions while retaining the current session', function (): void {
    config()->set('session.driver', 'database');

    $customer = User::factory()->withCustomerPin()->create([
        'session_version' => 3,
    ]);
    $customer->assignRole(RoleName::Customer->value);
    $this->actingAs($customer)
        ->withHeader('Origin', (string) config('app.url'))
        ->withSession(['customer_session_version' => 3]);
    DB::table('sessions')->insert([
        [
            'id' => 'stale-customer-session',
            'user_id' => $customer->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest stale session',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $this->putJson(route('api.v1.mobile.account.pin.update'), [
        'current_pin' => '123456',
        'pin' => '654321',
        'pin_confirmation' => '654321',
    ])->assertOk();

    $currentSessionId = app('session')->driver()->getId();

    $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
    $this->assertDatabaseMissing('sessions', ['id' => 'stale-customer-session']);
});

it('returns 401 and logs out a customer with a stale session version', function (): void {
    $customer = User::factory()->withCustomerPin()->create([
        'session_version' => 2,
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->actingAs($customer)
        ->withHeader('Origin', (string) config('app.url'))
        ->withSession(['customer_session_version' => 1])
        ->getJson(route('api.v1.mobile.context'));

    $response
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Sesi telah berakhir. Silakan masuk kembali.']);
    $this->assertGuest('web');
});

it('returns 401 and logs out a customer when the session version is missing', function (int $sessionVersion): void {
    $customer = User::factory()->withCustomerPin()->create([
        'session_version' => $sessionVersion,
    ]);
    $customer->assignRole(RoleName::Customer->value);

    $response = $this->actingAs($customer)
        ->withHeader('Origin', (string) config('app.url'))
        ->getJson(route('api.v1.mobile.context'));

    $response
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Sesi telah berakhir. Silakan masuk kembali.']);
    $this->assertGuest('web');
})->with([
    'default version' => 1,
    'advanced version' => 2,
]);
