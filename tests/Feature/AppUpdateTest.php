<?php

use App\Models\User;
use App\Services\AppUpdateService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
});

it('requires authentication to check for Android updates', function (): void {
    $this->getJson(route('api.v1.mobile.app-update.show'))->assertUnauthorized();
});

it('returns the installed changelog and a signed verified APK update', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.14',
            'body' => "- Added verified Android updates.\n- Fixed receipt sharing.",
            'published_at' => '2026-09-15T10:00:00Z',
            'assets' => [
                [
                    'id' => 140,
                    'name' => 'laundryhub-v1.0.14.apk',
                    'size' => 24_000_000,
                    'digest' => 'sha256:'.str_repeat('a', 64),
                ],
            ],
        ]),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson(route('api.v1.mobile.app-update.show', [
        'current_version' => '1.0.13',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.current_version', '1.0.13')
        ->assertJsonPath('data.latest_version', '1.0.14')
        ->assertJsonPath('data.latest_changelog.0', 'Added verified Android updates.')
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.check_available', true)
        ->assertJsonPath('data.apk_filename', 'laundryhub-v1.0.14.apk')
        ->assertJsonPath('data.apk_sha256', str_repeat('a', 64));

    expect($response->json('data.current_changelog'))->not->toBeEmpty();
    expect($response->json('data.download_url'))
        ->toBeString()
        ->toContain('/app-updates/140/laundryhub-v1.0.14.apk')
        ->toContain('signature=')
        ->not->toContain('private-release-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/laundryhub/releases/latest'
        && $request->hasHeader('Authorization', 'Bearer private-release-token')
    );
});

it('reads the APK checksum from the release checksum asset', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    $checksum = str_repeat('b', 64);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.14',
            'body' => '- Added an update.',
            'assets' => [
                ['id' => 140, 'name' => 'laundryhub-v1.0.14.apk', 'size' => 1_000],
                ['id' => 141, 'name' => 'SHA256SUMS.txt', 'size' => 100],
            ],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/141' => Http::response(
            $checksum.'  laundryhub-v1.0.14.apk',
        ),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.13']))
        ->assertOk()
        ->assertJsonPath('data.apk_sha256', $checksum);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/laundryhub/releases/assets/141'
        && $request->header('Accept') === ['application/octet-stream']
    );
});

it('reuses cached GitHub release metadata for different installed versions', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
        'services.github_releases.cache_seconds' => 900,
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.14',
            'body' => '- Added cached update checks.',
            'assets' => [
                [
                    'id' => 140,
                    'name' => 'laundryhub-v1.0.14.apk',
                    'size' => 24_000_000,
                    'digest' => 'sha256:'.str_repeat('a', 64),
                ],
            ],
        ]),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.12']))
        ->assertOk()
        ->assertJsonPath('data.update_available', true);
    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.14']))
        ->assertOk()
        ->assertJsonPath('data.update_available', false);

    Http::assertSentCount(1);
});

it('returns the GitHub release when the configured cache store is unavailable', function (): void {
    config()->set([
        'cache.default' => 'broken-app-update-cache',
        'cache.stores.broken-app-update-cache' => [
            'driver' => 'database',
            'connection' => 'broken-app-update-cache',
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => null,
        ],
        'database.connections.broken-app-update-cache' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.16',
            'body' => '- Fixed update checks when cache storage is unavailable.',
            'assets' => [
                [
                    'id' => 160,
                    'name' => 'laundryhub-v1.0.16.apk',
                    'size' => 24_000_000,
                    'digest' => 'sha256:'.str_repeat('c', 64),
                ],
            ],
        ]),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.15']))
        ->assertOk()
        ->assertJsonPath('data.latest_version', '1.0.16')
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.check_available', true);

    Http::assertSentCount(1);
});

it('returns a controlled unavailable state when both cache and GitHub are unavailable', function (): void {
    config()->set([
        'cache.default' => 'broken-app-update-cache',
        'cache.stores.broken-app-update-cache' => [
            'driver' => 'database',
            'connection' => 'broken-app-update-cache',
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => null,
        ],
        'database.connections.broken-app-update-cache' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([], 503),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.15']))
        ->assertOk()
        ->assertJsonPath('data.check_available', false)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Layanan GitHub sedang bermasalah. Coba lagi nanti.',
        );

    Http::assertSentCount(1);
});

it('uses the last successful release when GitHub is temporarily unavailable', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
        'services.github_releases.cache_seconds' => 1,
    ]);
    $requestCount = 0;
    Http::fake(function () use (&$requestCount) {
        $requestCount++;

        if ($requestCount === 1) {
            return Http::response([
                'tag_name' => 'v1.0.14',
                'body' => '- Added resilient update checks.',
                'assets' => [
                    [
                        'id' => 140,
                        'name' => 'laundryhub-v1.0.14.apk',
                        'size' => 24_000_000,
                        'digest' => 'sha256:'.str_repeat('a', 64),
                    ],
                ],
            ]);
        }

        return Http::response([], 503);
    });
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.13']))
        ->assertOk()
        ->assertJsonPath('data.latest_version', '1.0.14');
    $this->travel(2)->seconds();

    $this->getJson(route('api.v1.mobile.app-update.show', ['current_version' => '1.0.13']))
        ->assertOk()
        ->assertJsonPath('data.latest_version', '1.0.14')
        ->assertJsonPath('data.check_available', true)
        ->assertJsonPath('data.unavailable_reason', null);

    expect($requestCount)->toBe(2);
});

it('returns controlled unavailable states without exposing server errors', function (): void {
    Sanctum::actingAs(User::factory()->create());
    config()->set([
        'services.github_releases.repository' => null,
        'services.github_releases.token' => null,
    ]);

    $this->getJson(route('api.v1.mobile.app-update.show'))
        ->assertOk()
        ->assertJsonPath('data.current_version', '1.0.18')
        ->assertJsonPath('data.check_available', false)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Pengecekan pembaruan belum dikonfigurasi di server.',
        );

    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    Http::fake(['*' => Http::failedConnection()]);

    $this->getJson(route('api.v1.mobile.app-update.show'))
        ->assertOk()
        ->assertJsonPath('data.check_available', false)
        ->assertJsonPath(
            'data.unavailable_reason',
            'GitHub belum dapat dihubungi. Periksa koneksi server lalu coba lagi.',
        );
});

it('returns a controlled unavailable state when the update service fails unexpectedly', function (): void {
    Log::shouldReceive('error')
        ->once()
        ->andThrow(new RuntimeException('logging is unavailable'));
    $appUpdates = Mockery::mock(AppUpdateService::class);
    $appUpdates->shouldReceive('check')
        ->once()
        ->with('1.0.15')
        ->andThrow(new RuntimeException('private failure details'));
    $this->app->instance(AppUpdateService::class, $appUpdates);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson(route('api.v1.mobile.app-update.show', [
        'current_version' => '1.0.15',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.current_version', '1.0.15')
        ->assertJsonPath('data.latest_version', null)
        ->assertJsonPath('data.update_available', false)
        ->assertJsonPath('data.check_available', false)
        ->assertJsonPath(
            'data.unavailable_reason',
            'Rilis terbaru belum dapat diperiksa. Coba lagi nanti.',
        );
    expect($response->getContent())->not->toContain('private failure details');
});

it('returns a safe reason for GitHub HTTP failures', function (int $status, string $reason): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([], $status),
    ]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson(route('api.v1.mobile.app-update.show'));

    $response->assertOk()
        ->assertJsonPath('data.check_available', false)
        ->assertJsonPath('data.unavailable_reason', $reason);
    expect($response->getContent())->not->toContain('private-release-token');
})->with([
    '401 invalid token' => [
        401,
        'Token GitHub untuk pembaruan tidak valid atau sudah kedaluwarsa.',
    ],
    '403 denied or rate limited' => [
        403,
        'Akses GitHub ditolak atau batas permintaan telah tercapai. Coba lagi nanti.',
    ],
    '404 inaccessible release' => [
        404,
        'Repositori atau rilis GitHub tidak ditemukan, atau token tidak memiliki akses.',
    ],
    '429 rate limited' => [
        429,
        'Akses GitHub ditolak atau batas permintaan telah tercapai. Coba lagi nanti.',
    ],
]);

it('caches failed GitHub checks to avoid repeated requests', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
        'services.github_releases.cache_seconds' => 900,
    ]);
    $requestCount = 0;
    Http::fake(function () use (&$requestCount) {
        $requestCount++;

        return Http::response([], 401);
    });
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.mobile.app-update.show'))->assertOk();
    $this->getJson(route('api.v1.mobile.app-update.show'))->assertOk();

    expect($requestCount)->toBe(1);
});

it('proxies a signed APK download without exposing the GitHub token', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
    Http::fake([
        'https://api.github.com/repos/acme/laundryhub/releases/assets/140' => Http::response(
            'signed-apk-binary',
            200,
            ['Content-Type' => 'application/octet-stream'],
        ),
    ]);
    $url = URL::temporarySignedRoute(
        'app-updates.download',
        now()->addMinute(),
        ['asset' => 140, 'filename' => 'laundryhub-v1.0.14.apk'],
    );

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.android.package-archive')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertStreamedContent('signed-apk-binary');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/laundryhub/releases/assets/140'
        && $request->hasHeader('Authorization', 'Bearer private-release-token')
        && $request->header('Accept') === ['application/octet-stream']
    );
});

it('rejects missing tampered and expired download signatures', function (): void {
    $parameters = ['asset' => 140, 'filename' => 'laundryhub-v1.0.14.apk'];

    $this->get(route('app-updates.download', $parameters))->assertForbidden();

    $validUrl = URL::temporarySignedRoute('app-updates.download', now()->addMinute(), $parameters);
    $this->get($validUrl.'&tampered=1')->assertForbidden();

    $expiredUrl = URL::temporarySignedRoute('app-updates.download', now()->subMinute(), $parameters);
    $this->get($expiredUrl)->assertForbidden();
});
