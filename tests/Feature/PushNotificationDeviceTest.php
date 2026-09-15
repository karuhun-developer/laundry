<?php

use App\Models\PushNotificationDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function pushNotificationDeviceStoreRoute(): string
{
    return route('api.v1.mobile.push-notifications.devices.store');
}

function pushNotificationDeviceDestroyRoute(): string
{
    return route('api.v1.mobile.push-notifications.devices.destroy');
}

it('requires authentication to register or remove a notification device', function (): void {
    $payload = ['token' => 'guest-device-token', 'platform' => 'android'];

    $this->postJson(pushNotificationDeviceStoreRoute(), $payload)->assertUnauthorized();
    $this->deleteJson(pushNotificationDeviceDestroyRoute(), ['token' => $payload['token']])
        ->assertUnauthorized();
});

it('registers an authenticated device without exposing or storing its token in plaintext', function (): void {
    $user = User::factory()->create();
    $token = 'firebase-registration-token-with-sensitive-content';
    Sanctum::actingAs($user);

    $this->postJson(pushNotificationDeviceStoreRoute(), [
        'token' => $token,
        'platform' => 'android',
    ])->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'platform', 'last_seen_at']])
        ->assertJsonMissingPath('data.token')
        ->assertJsonPath('data.platform', 'android');

    $device = PushNotificationDevice::query()->sole();
    $storedToken = DB::table('push_notification_devices')->where('id', $device->id)->value('token');

    expect($device->user_id)->toBe($user->id)
        ->and($device->token)->toBe($token)
        ->and($device->token_hash)->toBe(hash('sha256', $token))
        ->and($storedToken)->toBeString()->not->toBe($token);
});

it('moves the same Firebase token to the most recently authenticated user', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $token = 'shared-firebase-token';

    Sanctum::actingAs($firstUser);
    $this->postJson(pushNotificationDeviceStoreRoute(), [
        'token' => $token,
        'platform' => 'android',
    ])->assertCreated();

    Sanctum::actingAs($secondUser);
    $this->postJson(pushNotificationDeviceStoreRoute(), [
        'token' => $token,
        'platform' => 'ios',
    ])->assertCreated();

    expect(PushNotificationDevice::query()->count())->toBe(1);
    $device = PushNotificationDevice::query()->sole();

    expect($device->user_id)->toBe($secondUser->id)
        ->and($device->platform)->toBe('ios')
        ->and($device->token)->toBe($token);
});

it('only removes a token owned by the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownToken = 'owned-device-token';
    $otherToken = 'other-device-token';
    PushNotificationDevice::factory()->for($user)->create([
        'token' => $ownToken,
        'token_hash' => hash('sha256', $ownToken),
    ]);
    PushNotificationDevice::factory()->for($otherUser)->create([
        'token' => $otherToken,
        'token_hash' => hash('sha256', $otherToken),
    ]);
    Sanctum::actingAs($user);

    $this->deleteJson(pushNotificationDeviceDestroyRoute(), ['token' => $otherToken])
        ->assertNoContent();
    $this->assertDatabaseHas('push_notification_devices', [
        'user_id' => $otherUser->id,
        'token_hash' => hash('sha256', $otherToken),
    ]);

    $this->deleteJson(pushNotificationDeviceDestroyRoute(), ['token' => $ownToken])
        ->assertNoContent();
    $this->assertDatabaseMissing('push_notification_devices', [
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $ownToken),
    ]);
});

it('validates Firebase tokens and supported platforms', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(pushNotificationDeviceStoreRoute(), [
        'token' => '',
        'platform' => 'windows',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'platform']);

    $this->postJson(pushNotificationDeviceStoreRoute(), [
        'token' => str_repeat('x', 4097),
        'platform' => 'android',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('uses a thirty day idle session lifetime', function (): void {
    expect(config('session.lifetime'))->toBe(43200);
});
