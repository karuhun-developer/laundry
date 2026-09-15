<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
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
});

it('returns controlled unavailable states without exposing server errors', function (): void {
    Sanctum::actingAs(User::factory()->create());
    config()->set([
        'services.github_releases.repository' => null,
        'services.github_releases.token' => null,
    ]);

    $this->getJson(route('api.v1.mobile.app-update.show'))
        ->assertOk()
        ->assertJsonPath('data.current_version', '1.0.14')
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
            'Rilis terbaru belum dapat diperiksa. Periksa koneksi lalu coba lagi.',
        );
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
        && $request->hasHeader('Accept', 'application/octet-stream')
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
