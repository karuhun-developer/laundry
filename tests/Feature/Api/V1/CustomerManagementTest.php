<?php

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function customerManagementBranchAdmin(Organization $organization): User
{
    $branchAdmin = User::factory()->for($organization)->create();
    $branchAdmin->assignRole(RoleName::BranchAdmin->value);

    return $branchAdmin;
}

function customerManagementCustomer(Organization $organization, array $attributes = []): User
{
    $customer = User::factory()->create(array_merge([
        'organization_id' => null,
        'whatsapp_phone' => '628'.fake()->unique()->numerify('##########'),
    ], $attributes));
    $customer->assignRole(RoleName::Customer->value);
    $customer->customerOrganizations()->attach($organization);

    return $customer;
}

it('creates a global customer with a one-time temporary PIN and organization membership', function (): void {
    $this->travelTo('2026-09-15 09:30:00');
    $organization = Organization::factory()->create();
    $branchAdmin = customerManagementBranchAdmin($organization);
    Sanctum::actingAs($branchAdmin);

    $response = $this->postJson('/api/v1/mobile/customers', [
        'name' => 'Budi Santoso',
        'whatsapp_phone' => '0812-3456-7890',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.customer.name', 'Budi Santoso')
        ->assertJsonPath('data.customer.email', null)
        ->assertJsonPath('data.customer.whatsapp_phone', '6281234567890')
        ->assertJsonPath('data.customer.must_change_pin', true)
        ->assertJsonPath('data.customer.pin_expires_at', '2026-09-16T09:30:00+07:00')
        ->assertJsonPath('data.credential.expires_at', '2026-09-16T09:30:00+07:00')
        ->assertJsonMissingPath('data.customer.pin_hash')
        ->assertJsonMissingPath('data.customer.temporary_pin');

    $temporaryPin = $response->json('data.credential.temporary_pin');
    expect($temporaryPin)->toBeString()->toMatch('/^\d{6}$/');

    $customer = User::query()->findOrFail($response->json('data.customer.id'));

    expect($customer->organization_id)->toBeNull()
        ->and($customer->email)->toBeNull()
        ->and($customer->password)->toBeNull()
        ->and($customer->must_change_pin)->toBeTrue()
        ->and($customer->pin_expires_at?->toIso8601String())->toBe('2026-09-16T09:30:00+07:00')
        ->and($customer->pin_hash)->not->toBe($temporaryPin)
        ->and(Hash::check($temporaryPin, (string) $customer->pin_hash))->toBeTrue()
        ->and($customer->getRoleNames()->all())->toBe([RoleName::Customer->value]);

    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);

    $activityPayload = Activity::query()->where('log_name', 'customers')->get()->toJson();
    expect($activityPayload)->not->toContain($temporaryPin);
});

it('resolves a same-phone unique conflict by linking the canonical customer atomically', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $customer = customerManagementCustomer($otherOrganization, [
        'name' => 'Existing Name',
        'email' => 'existing@example.test',
        'password' => 'existing-password',
        'whatsapp_phone' => '6281234567890',
        'pin_hash' => Hash::make('654321'),
        'pin_expires_at' => null,
        'must_change_pin' => false,
        'session_version' => 7,
    ]);
    $customer->refresh();
    $before = $customer->getRawOriginal();
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->postJson('/api/v1/mobile/customers', [
        'name' => 'Attempted Replacement',
        'whatsapp_phone' => '+62 812-3456-7890',
    ])->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.name', 'Existing Name')
        ->assertJsonPath('data.whatsapp_phone', '6281234567890')
        ->assertJsonPath('data.must_change_pin', false)
        ->assertJsonPath('data.pin_expires_at', null)
        ->assertJsonMissingPath('data.credential')
        ->assertJsonMissingPath('data.temporary_pin')
        ->assertJsonMissingPath('data.pin_hash');

    $customer->refresh();

    expect($customer->getRawOriginal())->toBe($before)
        ->and($customer->getRoleNames()->all())->toBe([RoleName::Customer->value]);

    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);
});

it('rejects phone collisions with staff and non-customer accounts', function (?string $roles): void {
    $organization = Organization::factory()->create();
    $account = User::factory()->create(['whatsapp_phone' => '6281234567890']);

    if ($roles !== null) {
        $account->assignRole(explode(',', $roles));
    }

    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->postJson('/api/v1/mobile/customers', [
        'name' => 'Collision',
        'whatsapp_phone' => '081234567890',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('whatsapp_phone');

    $this->assertDatabaseMissing('customer_organization', [
        'customer_id' => $account->id,
        'organization_id' => $organization->id,
    ]);
})->with([
    RoleName::Superadmin->value,
    RoleName::Owner->value,
    RoleName::BranchAdmin->value,
    RoleName::Customer->value.','.RoleName::Owner->value,
    null,
]);

it('updates only a linked customer with a normalized unique phone', function (): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'name' => 'Before',
        'whatsapp_phone' => '6281234567890',
        'pin_expires_at' => '2026-09-23 12:15:00',
        'must_change_pin' => true,
    ]);
    User::factory()->create(['whatsapp_phone' => '6289876543210']);
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->patchJson("/api/v1/mobile/customers/{$customer->id}", [
        'name' => 'After',
        'whatsapp_phone' => '0813 4567 8901',
    ])->assertOk()
        ->assertJsonPath('data.name', 'After')
        ->assertJsonPath('data.whatsapp_phone', '6281345678901')
        ->assertJsonPath('data.must_change_pin', true)
        ->assertJsonPath('data.pin_expires_at', '2026-09-23T12:15:00+07:00')
        ->assertJsonMissingPath('data.pin_hash')
        ->assertJsonMissingPath('data.temporary_pin');

    $this->patchJson("/api/v1/mobile/customers/{$customer->id}", [
        'whatsapp_phone' => '0987-6543-210',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('whatsapp_phone');

    expect($customer->refresh()->name)->toBe('After')
        ->and($customer->whatsapp_phone)->toBe('6281345678901');
});

it('returns 422 for an empty update without mutating the customer or activity log', function (): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, ['name' => 'Unchanged']);
    $updatedAt = $customer->updated_at;
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->patchJson("/api/v1/mobile/customers/{$customer->id}", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'whatsapp_phone']);

    expect($customer->refresh()->name)->toBe('Unchanged')
        ->and($customer->updated_at?->equalTo($updatedAt))->toBeTrue();
    $this->assertDatabaseMissing('activity_log', [
        'log_name' => 'customers',
        'description' => 'customer updated',
        'subject_id' => $customer->id,
    ]);
});

it('rejects malformed customer create and update input', function (string $method, array $payload, array $errors): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'name' => 'Original',
        'whatsapp_phone' => '6281234567890',
    ]);
    Sanctum::actingAs(customerManagementBranchAdmin($organization));
    $url = $method === 'POST'
        ? '/api/v1/mobile/customers'
        : "/api/v1/mobile/customers/{$customer->id}";

    $this->json($method, $url, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);

    expect($customer->refresh()->name)->toBe('Original')
        ->and($customer->whatsapp_phone)->toBe('6281234567890');
})->with([
    'create unknown field' => ['POST', ['name' => 'Valid', 'whatsapp_phone' => '081234567890', 'role' => 'owner'], ['role']],
    'create empty name' => ['POST', ['name' => '', 'whatsapp_phone' => '081234567890'], ['name']],
    'create non-string name' => ['POST', ['name' => ['Invalid'], 'whatsapp_phone' => '081234567890'], ['name']],
    'create overlong name' => ['POST', ['name' => str_repeat('a', 256), 'whatsapp_phone' => '081234567890'], ['name']],
    'create non-string phone' => ['POST', ['name' => 'Valid', 'whatsapp_phone' => 81234567890], ['whatsapp_phone']],
    'create invalid phone' => ['POST', ['name' => 'Valid', 'whatsapp_phone' => 'not-a-phone'], ['whatsapp_phone']],
    'update unknown field' => ['PATCH', ['email' => 'takeover@example.test'], ['email']],
    'update null name' => ['PATCH', ['name' => null], ['name']],
    'update empty name' => ['PATCH', ['name' => ''], ['name']],
    'update non-string name' => ['PATCH', ['name' => ['Invalid']], ['name']],
    'update overlong name' => ['PATCH', ['name' => str_repeat('a', 256)], ['name']],
    'update non-string phone' => ['PATCH', ['whatsapp_phone' => 81234567890], ['whatsapp_phone']],
    'update invalid phone' => ['PATCH', ['whatsapp_phone' => 'not-a-phone'], ['whatsapp_phone']],
]);

it('rejects unknown fields on PIN reset', function (): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization);
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->postJson("/api/v1/mobile/customers/{$customer->id}/reset-pin", [
        'unexpected' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('unexpected');

    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);
});

it('resets the temporary PIN once and invalidates every customer session', function (): void {
    $this->travelTo('2026-09-15 10:45:00');
    config()->set('session.driver', 'database');
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'pin_hash' => Hash::make('123456'),
        'pin_expires_at' => null,
        'must_change_pin' => false,
        'session_version' => 4,
    ]);
    $otherCustomer = User::factory()->create();

    foreach ([['customer-session-1', $customer->id], ['customer-session-2', $customer->id], ['other-session', $otherCustomer->id]] as [$id, $userId]) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $response = $this->postJson("/api/v1/mobile/customers/{$customer->id}/reset-pin");

    $response->assertOk()
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.credential.expires_at', '2026-09-16T10:45:00+07:00')
        ->assertJsonMissingPath('data.customer.pin_hash');

    $temporaryPin = $response->json('data.credential.temporary_pin');
    $customer->refresh();

    expect($temporaryPin)->toBeString()->toMatch('/^\d{6}$/')
        ->and($customer->pin_hash)->not->toBe($temporaryPin)
        ->and(Hash::check($temporaryPin, (string) $customer->pin_hash))->toBeTrue()
        ->and($customer->must_change_pin)->toBeTrue()
        ->and($customer->pin_expires_at?->toIso8601String())->toBe('2026-09-16T10:45:00+07:00')
        ->and($customer->session_version)->toBe(5);

    $this->assertDatabaseMissing('sessions', ['id' => 'customer-session-1']);
    $this->assertDatabaseMissing('sessions', ['id' => 'customer-session-2']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);

    $activityPayload = Activity::query()->where('log_name', 'customers')->get()->toJson();
    expect($activityPayload)->not->toContain($temporaryPin);
});

it('exposes PIN state while keeping ordinary customer responses free of credentials', function (): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'pin_hash' => Hash::make('123456'),
        'pin_expires_at' => '2026-09-24 13:30:00',
        'must_change_pin' => true,
    ]);
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->getJson("/api/v1/organizations/{$organization->slug}/customers")
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->id)
        ->assertJsonPath('data.0.must_change_pin', true)
        ->assertJsonPath('data.0.pin_expires_at', '2026-09-24T13:30:00+07:00')
        ->assertJsonMissingPath('data.0.pin_hash')
        ->assertJsonMissingPath('data.0.pin')
        ->assertJsonMissingPath('data.0.temporary_pin');
});

it('never stores plaintext PIN values in customer activity properties', function (): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'pin_hash' => Hash::make('123456'),
    ]);
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $response = $this->postJson("/api/v1/mobile/customers/{$customer->id}/reset-pin")
        ->assertOk();

    $temporaryPin = $response->json('data.credential.temporary_pin');
    $activityProperties = Activity::query()
        ->where('log_name', 'customers')
        ->get(['properties'])
        ->toJson();

    expect($activityProperties)->not->toContain($temporaryPin)
        ->and($activityProperties)->not->toContain('pin_hash');
});

it('hides cross-tenant customers from update and reset operations', function (string $method, string $suffix): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $customer = customerManagementCustomer($otherOrganization, [
        'name' => 'Protected',
        'whatsapp_phone' => '6281234567890',
        'pin_hash' => Hash::make('123456'),
        'session_version' => 2,
    ]);
    $customer->refresh();
    $before = $customer->getRawOriginal();
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $payload = $method === 'PATCH' ? ['name' => 'Compromised'] : [];
    $this->json($method, "/api/v1/mobile/customers/{$customer->id}{$suffix}", $payload)
        ->assertNotFound();

    expect($customer->refresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $otherOrganization->id,
    ]);
})->with([
    ['PATCH', ''],
    ['POST', '/reset-pin'],
]);

it('does not expose an endpoint for removing a customer from an organization', function (): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization);
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $this->deleteJson("/api/v1/mobile/customers/{$customer->id}")
        ->assertMethodNotAllowed();

    $this->assertDatabaseHas('users', ['id' => $customer->id]);
    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);
});

it('denies global profile and PIN changes for a customer shared by organizations', function (string $method, string $suffix): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'name' => 'Shared Customer',
        'pin_hash' => Hash::make('123456'),
        'session_version' => 3,
    ]);
    $customer->customerOrganizations()->attach($otherOrganization);
    $before = $customer->refresh()->getRawOriginal();
    Sanctum::actingAs(customerManagementBranchAdmin($organization));

    $payload = $method === 'PATCH' ? ['name' => 'Taken Over'] : [];
    $this->json($method, "/api/v1/mobile/customers/{$customer->id}{$suffix}", $payload)
        ->assertNotFound();

    expect($customer->refresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('customer_organization', 2);
    $this->assertDatabaseMissing('activity_log', [
        'log_name' => 'customers',
        'subject_id' => $customer->id,
        'description' => $method === 'PATCH' ? 'customer updated' : 'customer pin reset',
    ]);
})->with([
    ['PATCH', ''],
    ['POST', '/reset-pin'],
]);

it('requires authentication for every customer management endpoint', function (string $method, string $path, array $payload): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization);
    $url = str_replace(
        ['{organization}', '{customer}'],
        [$organization->slug, (string) $customer->id],
        $path,
    );

    $this->json($method, $url, $payload)
        ->assertUnauthorized();
})->with([
    ['GET', '/api/v1/organizations/{organization}/customers', []],
    ['POST', '/api/v1/mobile/customers', ['name' => 'Unauthorized', 'whatsapp_phone' => '081234567890']],
    ['PATCH', '/api/v1/mobile/customers/{customer}', ['name' => 'Unauthorized']],
    ['POST', '/api/v1/mobile/customers/{customer}/reset-pin', []],
]);

it('denies unsupported actors across customer management endpoints', function (?RoleName $role, bool $hasOrganization, string $method, string $path, array $payload): void {
    $organization = Organization::factory()->create();
    $customer = customerManagementCustomer($organization, [
        'name' => 'Protected',
        'pin_hash' => Hash::make('123456'),
        'session_version' => 4,
    ]);
    $actor = User::factory()->create([
        'organization_id' => $hasOrganization ? $organization->id : null,
    ]);

    if ($role !== null) {
        $actor->assignRole($role->value);
    }

    Sanctum::actingAs($actor);
    $userCount = User::query()->count();
    $activityCount = Activity::query()->count();
    $url = str_replace(
        ['{organization}', '{customer}'],
        [$organization->slug, (string) $customer->id],
        $path,
    );

    $expectedStatus = match (true) {
        $method === 'GET' => 403,
        $method === 'POST' && $path === '/api/v1/mobile/customers' => 403,
        default => 404,
    };

    $this->json($method, $url, $payload)->assertStatus($expectedStatus);

    expect($customer->refresh()->name)->toBe('Protected')
        ->and(Hash::check('123456', (string) $customer->pin_hash))->toBeTrue()
        ->and($customer->session_version)->toBe(4)
        ->and(User::query()->count())->toBe($userCount)
        ->and(Activity::query()->count())->toBe($activityCount);
    $this->assertDatabaseHas('customer_organization', [
        'customer_id' => $customer->id,
        'organization_id' => $organization->id,
    ]);
})->with([
    'owner list' => [RoleName::Owner, true, 'GET', '/api/v1/organizations/{organization}/customers', []],
    'owner create' => [RoleName::Owner, true, 'POST', '/api/v1/mobile/customers', ['name' => 'Forbidden', 'whatsapp_phone' => '081211111111']],
    'owner update' => [RoleName::Owner, true, 'PATCH', '/api/v1/mobile/customers/{customer}', ['name' => 'Forbidden']],
    'owner reset' => [RoleName::Owner, true, 'POST', '/api/v1/mobile/customers/{customer}/reset-pin', []],
    'customer list' => [RoleName::Customer, false, 'GET', '/api/v1/organizations/{organization}/customers', []],
    'customer create' => [RoleName::Customer, false, 'POST', '/api/v1/mobile/customers', ['name' => 'Forbidden', 'whatsapp_phone' => '081211111111']],
    'customer update' => [RoleName::Customer, false, 'PATCH', '/api/v1/mobile/customers/{customer}', ['name' => 'Forbidden']],
    'customer reset' => [RoleName::Customer, false, 'POST', '/api/v1/mobile/customers/{customer}/reset-pin', []],
    'roleless list' => [null, true, 'GET', '/api/v1/organizations/{organization}/customers', []],
    'roleless create' => [null, true, 'POST', '/api/v1/mobile/customers', ['name' => 'Forbidden', 'whatsapp_phone' => '081211111111']],
    'roleless update' => [null, true, 'PATCH', '/api/v1/mobile/customers/{customer}', ['name' => 'Forbidden']],
    'roleless reset' => [null, true, 'POST', '/api/v1/mobile/customers/{customer}/reset-pin', []],
    'branch admin without organization list' => [RoleName::BranchAdmin, false, 'GET', '/api/v1/organizations/{organization}/customers', []],
    'branch admin without organization create' => [RoleName::BranchAdmin, false, 'POST', '/api/v1/mobile/customers', ['name' => 'Forbidden', 'whatsapp_phone' => '081211111111']],
    'branch admin without organization update' => [RoleName::BranchAdmin, false, 'PATCH', '/api/v1/mobile/customers/{customer}', ['name' => 'Forbidden']],
    'branch admin without organization reset' => [RoleName::BranchAdmin, false, 'POST', '/api/v1/mobile/customers/{customer}/reset-pin', []],
]);
