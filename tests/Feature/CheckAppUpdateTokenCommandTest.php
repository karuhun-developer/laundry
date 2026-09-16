<?php

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
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
});

it('diagnoses 404 when token lacks repo scope', function (): void {
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
        ->expectsOutputToContain('Scope token aktif: (KOSONG / 0 SCOPE)')
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
                ['name' => 'laundryhub-v1.0.18.apk'],
            ],
        ], 200),
    ]);

    $this->artisan('app:check-update-token')
        ->expectsOutputToContain('Terautentikasi sebagai: johndoe (John Doe)')
        ->expectsOutputToContain('Scope token aktif: repo, read:org')
        ->expectsOutputToContain('[BERHASIL] Rilis terbaru dapat diakses!')
        ->expectsOutputToContain('Tag rilis:    v1.0.18')
        ->assertSuccessful();
});
