<?php

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'private-release-token',
    ]);
});

it('fails when repository or token is unconfigured', function (): void {
    config()->set([
        'services.github_releases.repository' => null,
        'services.github_releases.token' => null,
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('services.github_releases.repository belum diatur')
        ->assertFailed();
});

it('fails when token is invalid', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'invalid-token',
    ]);

    Http::fake([
        'https://api.github.com/user' => Http::response([], 401),
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('Token tidak valid atau sudah kedaluwarsa (HTTP 401)')
        ->assertFailed();

    Http::assertSentCount(1);
});

it('diagnoses 404 without assuming empty scopes mean no private access', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'token-without-scope',
    ]);

    Http::fake([
        'https://api.github.com/user' => Http::response([
            'login' => 'johndoe',
            'name' => 'John Doe',
        ], 200, ['x-oauth-scopes' => '']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response(
            ['message' => 'Not Found'],
            404,
            ['x-accepted-oauth-scopes' => 'repo'],
        ),
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('Terautentikasi sebagai: johndoe (John Doe)')
        ->expectsOutputToContain('Scope OAuth tidak dilaporkan')
        ->doesntExpectOutputToContain('Token tanpa scope tidak bisa mengakses repositori privat')
        ->expectsOutputToContain('[GAGAL] Repositori atau rilis tidak ditemukan (HTTP 404)')
        ->expectsOutputToContain('Scope yang disyaratkan GitHub: repo')
        ->assertFailed();
});

it('succeeds when release is accessible', function (): void {
    config()->set([
        'services.github_releases.repository' => 'acme/laundryhub',
        'services.github_releases.token' => 'valid-repo-token',
    ]);

    Http::fake([
        'https://api.github.com/user' => Http::response([
            'login' => 'johndoe',
            'name' => 'John Doe',
        ], 200, ['x-oauth-scopes' => 'repo, read:org']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.18',
            'published_at' => '2026-09-15T19:00:00Z',
            'assets' => [
                ['id' => 180, 'name' => 'laundryhub-v1.0.18.apk'],
            ],
        ], 200),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/180' => Http::response(
            "PK\x03\x04apk-content", 200, ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('Terautentikasi sebagai: johndoe (John Doe)')
        ->expectsOutputToContain('Scope token aktif: repo, read:org')
        ->expectsOutputToContain('[BERHASIL] Rilis terbaru dapat diakses!')
        ->expectsOutputToContain('Tag rilis:    v1.0.18')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/laundryhub/releases/assets/180'
        && $request->hasHeader('Accept', 'application/octet-stream')
        && $request->hasHeader('Authorization', 'Bearer valid-repo-token')
    );
});

it('fails without sending requests when only the token is missing', function (): void {
    config()->set('services.github_releases.token', ' ');

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('GITHUB_RELEASE_TOKEN')
        ->assertFailed();

    Http::assertNothingSent();
});

it('fails when release metadata has no valid stable APK', function (array $release): void {
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response($release),
    ]);

    $this->artisan('app:check-update-token')->assertFailed();

    Http::assertSentCount(2);
})->with([
    'empty metadata' => [[]],
    'no assets' => [['tag_name' => 'v1.0.20', 'assets' => []]],
    'non-array assets' => [['tag_name' => 'v1.0.20', 'assets' => 'invalid']],
    'checksum only' => [['tag_name' => 'v1.0.20', 'assets' => [['id' => 201, 'name' => 'SHA256SUMS.txt']]]],
    'wrong APK version' => [['tag_name' => 'v1.0.20', 'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.19.apk']]]],
    'missing asset id' => [['tag_name' => 'v1.0.20', 'assets' => [['name' => 'laundryhub-v1.0.20.apk']]]],
    'string asset id' => [['tag_name' => 'v1.0.20', 'assets' => [['id' => '200', 'name' => 'laundryhub-v1.0.20.apk']]]],
    'negative asset id' => [['tag_name' => 'v1.0.20', 'assets' => [['id' => -200, 'name' => 'laundryhub-v1.0.20.apk']]]],
    'invalid tag' => [['tag_name' => 'latest', 'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']]]],
    'prerelease tag' => [['tag_name' => 'v1.0.20-beta', 'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20-beta.apk']]]],
    'prerelease flag' => [['tag_name' => 'v1.0.20', 'prerelease' => true, 'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']]]],
    'draft release' => [['tag_name' => 'v1.0.20', 'draft' => true, 'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']]]],
]);

it('succeeds with a fine-grained token without OAuth scopes', function (array $headers): void {
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe'], 200, $headers),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::response(
            "PK\x03\x04apk-content", 200, ['Content-Type' => 'application/vnd.android.package-archive'],
        ),
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('Scope OAuth tidak dilaporkan')
        ->doesntExpectOutputToContain('Token tanpa scope tidak bisa mengakses repositori privat')
        ->doesntExpectOutputToContain('private-release-token')
        ->assertSuccessful();

    Http::assertSentCount(3);
})->with([
    'absent scopes header' => [[]],
    'empty scopes header' => [['x-oauth-scopes' => '']],
]);

it('succeeds for an installation token when user lookup returns HTTP 403', function (): void {
    Http::fake([
        'https://api.github.com/user' => Http::response(['message' => 'Resource not accessible by integration'], 403),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::response(
            "PK\x03\x04apk-content", 200, ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('HTTP 403')
        ->expectsOutputToContain('[BERHASIL]')
        ->assertSuccessful();

    Http::assertSentCount(3);
});

it('reads a bounded binary sample and closes the APK stream', function (int $chunkSize): void {
    $body = Utils::streamFor("PK\x03\x04".str_repeat('apk-content', 1000));
    $bytesRead = 0;
    $stream = FnStream::decorate($body, [
        'read' => function (int $length) use ($body, $chunkSize, &$bytesRead): string {
            $chunk = $body->read(min($length, $chunkSize));
            $bytesRead += strlen($chunk);

            return $chunk;
        },
        'getContents' => fn (): never => throw new RuntimeException('Full APK transfer attempted.'),
        '__toString' => fn (): never => throw new RuntimeException('Full APK transfer attempted.'),
    ]);
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => function (Request $request, array $options) use ($stream): PromiseInterface {
            expect($options['stream'] ?? false)->toBeTrue();

            return Http::response($stream, 200, ['Content-Type' => 'application/octet-stream']);
        },
    ]);

    $this->artisan('app:check-update-token')->assertSuccessful();

    expect($bytesRead)->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(512);
    expect($body->isReadable())->toBeFalse();
})->with(['single read' => [512], 'partial reads' => [1]]);

it('rejects and closes non-binary successful asset responses', function (string $body, string $contentType): void {
    $stream = Utils::streamFor($body);
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::response(
            $stream, 200, ['Content-Type' => $contentType],
        ),
    ]);

    $this->artisan('app:check-update-token')->assertFailed();

    expect($stream->isReadable())->toBeFalse();
})->with([
    'JSON metadata' => ['{"id":200,"name":"laundryhub-v1.0.20.apk"}', 'application/json'],
    'JSON with binary content type' => ['{"message":"Not Found"}', 'application/octet-stream'],
    'HTML error' => ['<html>Access denied</html>', 'text/html'],
    'plain error' => ['Access denied', 'application/octet-stream'],
    'empty body' => ['', 'application/octet-stream'],
    'truncated signature' => ['PK', 'application/octet-stream'],
    'JSON content type' => ["PK\x03\x04payload", 'application/json'],
]);

it('diagnoses asset HTTP failures and closes their streams', function (int $status): void {
    $stream = Utils::streamFor('{"message":"private-release-token"}');
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::response($stream, $status),
    ]);

    expect(Artisan::call('app:check-update-token'))->toBe(1);
    expect(Artisan::output())
        ->toContain("HTTP {$status}")
        ->not->toContain('private-release-token');

    expect($stream->isReadable())->toBeFalse();
})->with([401, 403, 404, 429, 500, 302, 204]);

it('diagnoses release HTTP failures without exposing response bodies', function (int $status): void {
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response(['message' => 'private-release-token'], $status),
    ]);

    expect(Artisan::call('app:check-update-token'))->toBe(1);
    expect(Artisan::output())
        ->toContain("HTTP {$status}")
        ->not->toContain('private-release-token');
})->with([401, 403, 429, 500]);

it('reports release connection timeouts without exposing exceptions', function (): void {
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::failedConnection('cURL error 28: Authorization: Bearer private-release-token'),
    ]);

    expect(Artisan::call('app:check-update-token'))->toBe(1);
    expect(Artisan::output())
        ->toContain('Gagal menghubungi GitHub API')
        ->not->toContain('private-release-token', 'cURL error');
});

it('continues after an informational user lookup timeout', function (): void {
    Http::fake([
        'https://api.github.com/user' => Http::failedConnection('cURL error 28: private-release-token'),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::response(
            "PK\x03\x04apk-content", 200, ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    expect(Artisan::call('app:check-update-token'))->toBe(0);
    expect(Artisan::output())->not->toContain('private-release-token', 'cURL error');

    Http::assertSentCount(3);
});

it('reports asset connection timeouts without exposing exceptions', function (): void {
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::failedConnection('cURL error 28: private-release-token'),
    ]);

    expect(Artisan::call('app:check-update-token'))->toBe(1);
    expect(Artisan::output())
        ->toContain('koneksi')
        ->not->toContain('private-release-token', 'cURL error');
});

it('closes the APK stream when a bounded read times out', function (): void {
    $body = Utils::streamFor("PK\x03\x04apk-content");
    $stream = FnStream::decorate($body, [
        'read' => fn (int $length): never => throw new RuntimeException('Read timeout: private-release-token'),
    ]);
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'johndoe']),
        'https://api.github.com/repos/acme/laundryhub/releases/latest' => Http::response([
            'tag_name' => 'v1.0.20',
            'assets' => [['id' => 200, 'name' => 'laundryhub-v1.0.20.apk']],
        ]),
        'https://api.github.com/repos/acme/laundryhub/releases/assets/200' => Http::response(
            $stream, 200, ['Content-Type' => 'application/octet-stream'],
        ),
    ]);

    expect(Artisan::call('app:check-update-token'))->toBe(1);
    expect(Artisan::output())->not->toContain('private-release-token', 'Read timeout:');

    expect($body->isReadable())->toBeFalse();
});
