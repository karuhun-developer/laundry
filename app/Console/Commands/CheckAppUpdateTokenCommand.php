<?php

namespace App\Console\Commands;

use App\Services\AppUpdateService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckAppUpdateTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-update-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa status token GitHub dan akses ke rilis repositori aplikasi.';

    /**
     * Execute the console command.
     */
    public function handle(AppUpdateService $appUpdates): int
    {
        $repository = config('services.github_releases.repository');
        $token = config('services.github_releases.token');

        $this->info('=== Pemeriksaan Token Pembaruan Aplikasi ===');

        if (! is_string($repository) || trim($repository) === '') {
            $this->error('Konfigurasi services.github_releases.repository belum diatur.');

            return self::FAILURE;
        }

        if (! is_string($token) || trim($token) === '') {
            $this->error('Konfigurasi services.github_releases.token (GITHUB_RELEASE_TOKEN) belum diatur.');

            return self::FAILURE;
        }

        $repository = trim($repository);
        $token = trim($token);

        $this->line("Repositori: {$repository}");
        $this->line('Token:      '.$this->maskToken($token));
        $this->newLine();

        $this->line('1. Memeriksa identitas pemilik token...');
        $userResponse = null;

        try {
            $userResponse = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'LaundryHub-App-Updater',
                ])
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://api.github.com/user');
        } catch (Throwable) {
            $this->warn('   Identitas token belum dapat diperiksa. Periksa koneksi atau batas waktu GitHub API; pemeriksaan rilis dilanjutkan.');
        }

        if ($userResponse?->status() === 401) {
            $this->error('Token tidak valid atau sudah kedaluwarsa (HTTP 401).');
            $this->line('Buat token baru di https://github.com/settings/tokens lalu perbarui .env.');

            return self::FAILURE;
        }

        if ($userResponse?->successful()) {
            $userData = $userResponse->json();
            $username = is_string($userData['login'] ?? null) ? $userData['login'] : 'unknown';
            $name = is_string($userData['name'] ?? null) ? $userData['name'] : '';

            $this->info("   Terautentikasi sebagai: {$username}".($name !== '' ? " ({$name})" : ''));
        } elseif ($userResponse !== null) {
            $this->warn("   Identitas token tidak tersedia (HTTP {$userResponse->status()}). Endpoint /user tidak wajib untuk token instalasi; pemeriksaan rilis dilanjutkan.");
        }

        $scopes = (string) $userResponse?->header('x-oauth-scopes');
        $scopeList = array_filter(array_map('trim', explode(',', $scopes)));

        if ($scopeList === []) {
            $this->line('   Scope OAuth tidak dilaporkan; ini tidak menentukan akses token fine-grained atau instalasi.');
        } else {
            $this->line('   Scope token aktif: '.implode(', ', $scopeList));
        }

        $this->newLine();
        $this->line("2. Memeriksa akses rilis terbaru ke {$repository}...");

        try {
            $releaseResponse = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'LaundryHub-App-Updater',
                ])
                ->connectTimeout(5)
                ->timeout(15)
                ->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (Throwable) {
            $this->error('Gagal menghubungi GitHub API. Periksa koneksi atau batas waktu permintaan lalu coba lagi.');

            return self::FAILURE;
        }

        $status = $releaseResponse->status();

        if ($status === 200) {
            $releaseData = $releaseResponse->json();
            $tagName = is_array($releaseData) ? ($releaseData['tag_name'] ?? null) : null;

            if (! is_string($tagName)
                || preg_match('/^v?([0-9]+\.[0-9]+\.[0-9]+)\z/', $tagName, $matches) !== 1
                || ($releaseData['draft'] ?? false) !== false
                || ($releaseData['prerelease'] ?? false) !== false
            ) {
                $this->error('   [GAGAL] Tag rilis terbaru bukan versi stabil yang valid.');

                return self::FAILURE;
            }

            $assets = is_array($releaseData['assets'] ?? null) ? $releaseData['assets'] : [];
            $publishedAt = is_string($releaseData['published_at'] ?? null) ? $releaseData['published_at'] : '-';

            $this->line("   Tag rilis:    {$tagName}");
            $this->line('   Jumlah aset:  '.count($assets));
            $this->line("   Dipublikasi:  {$publishedAt}");

            $filename = "laundryhub-v{$matches[1]}.apk";

            foreach ($assets as $asset) {
                if (is_array($asset)
                    && ($asset['name'] ?? null) === $filename
                    && is_int($asset['id'] ?? null)
                    && $asset['id'] > 0
                ) {
                    $this->line("3. Memeriksa unduhan biner {$filename}...");

                    return $this->checkAssetAccess($appUpdates, $asset['id']);
                }
            }

            $this->error("   [GAGAL] Rilis tidak memiliki APK {$filename} dengan ID aset yang valid.");

            return self::FAILURE;
        }

        if ($status === 401) {
            $this->error('   [GAGAL] Token tidak valid atau sudah kedaluwarsa (HTTP 401).');

            return self::FAILURE;
        }

        if ($status === 404) {
            $acceptedScopes = (string) $releaseResponse->header('x-accepted-oauth-scopes');

            $this->error('   [GAGAL] Repositori atau rilis tidak ditemukan (HTTP 404).');
            $this->line('   Penyebab:');
            $this->line('   - Repositori atau rilis belum tersedia, atau token belum diberi akses ke repositori.');
            if ($acceptedScopes !== '') {
                $this->line("   - Scope yang disyaratkan GitHub: {$acceptedScopes}");
            }
            $this->line("   - Jika ini repositori organisasi, pastikan token telah diotorisasi untuk organisasi '{$this->organizationFromRepository($repository)}'.");
            $this->newLine();
            $this->line('   Solusi:');
            $this->line('   1. Buka https://github.com/settings/tokens');
            $this->line("   2. Berikan akses repositori dan Contents: read untuk token fine-grained/instalasi, atau scope 'repo' untuk token klasik.");
            $this->line("   3. Simpan token ke .env (GITHUB_RELEASE_TOKEN=...) lalu jalankan 'php artisan config:clear'.");

            return self::FAILURE;
        }

        if ($status === 403 || $status === 429) {
            $this->error("   [GAGAL] Akses ditolak atau batas kuota permintaan GitHub tercapai (HTTP {$status}).");

            return self::FAILURE;
        }

        $this->error("   [GAGAL] GitHub API mengembalikan status HTTP {$status}.");

        return self::FAILURE;
    }

    private function checkAssetAccess(AppUpdateService $appUpdates, int $assetId): int
    {
        $response = null;

        try {
            try {
                try {
                    $response = $appUpdates->downloadAsset($assetId);
                } catch (RequestException $exception) {
                    $response = $exception->response;
                }

                $status = $response->status();

                if ($status !== 200) {
                    $reason = match ($status) {
                        401 => 'Token tidak valid atau sudah kedaluwarsa',
                        403, 429 => 'Akses ditolak atau batas kuota permintaan GitHub tercapai',
                        404 => 'Aset APK tidak ditemukan atau token tidak memiliki akses',
                        default => 'Unduhan APK mengembalikan status yang tidak diharapkan',
                    };
                    $this->error("   [GAGAL] {$reason} (HTTP {$status}).");

                    return self::FAILURE;
                }

                $contentType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
                $stream = $response->toPsrResponse()->getBody();
                $signature = '';

                while (strlen($signature) < 4) {
                    $chunk = $stream->read(4 - strlen($signature));

                    if ($chunk === '') {
                        break;
                    }

                    $signature .= $chunk;
                }

                if ($signature !== "PK\x03\x04"
                    || ! in_array($contentType, ['', 'application/octet-stream', 'application/vnd.android.package-archive', 'application/zip'], true)
                ) {
                    $this->error('   [GAGAL] Respons unduhan bukan biner APK yang valid (kosong, JSON, atau respons galat).');

                    return self::FAILURE;
                }
            } finally {
                $response?->close();
            }
        } catch (Throwable) {
            $this->error('   [GAGAL] Unduhan APK belum dapat diperiksa. Periksa koneksi atau batas waktu permintaan lalu coba lagi.');

            return self::FAILURE;
        }

        $this->info('   [BERHASIL] Rilis terbaru dapat diakses!');
        $this->info('   Biner APK dapat diunduh (pemeriksaan awal 4 byte).');

        return self::SUCCESS;
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return '***';
        }

        return substr($token, 0, 4).'...'.substr($token, -4);
    }

    private function organizationFromRepository(string $repository): string
    {
        $parts = explode('/', $repository);

        return $parts[0] !== '' ? $parts[0] : $repository;
    }
}
