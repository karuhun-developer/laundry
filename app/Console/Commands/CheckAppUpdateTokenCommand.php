<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
    public function handle(): int
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

        try {
            $userResponse = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'LaundryHub-App-Updater',
                ])
                ->timeout(10)
                ->get('https://api.github.com/user');
        } catch (Throwable $e) {
            $this->error('Gagal menghubungi GitHub API: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($userResponse->status() === 401) {
            $this->error('Token tidak valid atau sudah kedaluwarsa (HTTP 401).');
            $this->line('Buat token baru di https://github.com/settings/tokens lalu perbarui .env.');

            return self::FAILURE;
        }

        if (! $userResponse->successful()) {
            $this->error("GitHub API mengembalikan status HTTP {$userResponse->status()} saat memeriksa pengguna.");

            return self::FAILURE;
        }

        $userData = $userResponse->json();
        $username = is_array($userData) && isset($userData['login']) ? (string) $userData['login'] : 'unknown';
        $name = is_array($userData) && isset($userData['name']) ? (string) $userData['name'] : '';

        $this->info("   Terautentikasi sebagai: {$username}".($name !== '' ? " ({$name})" : ''));

        $scopes = (string) $userResponse->header('x-oauth-scopes');
        $scopeList = array_filter(array_map('trim', explode(',', $scopes)));

        if ($scopeList === []) {
            $this->warn('   Scope token aktif: (KOSONG / 0 SCOPE)');
            $this->warn('   Perhatian: Token tanpa scope tidak bisa mengakses repositori privat!');
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
                ->timeout(15)
                ->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (Throwable $e) {
            $this->error('Gagal menghubungi GitHub API: '.$e->getMessage());

            return self::FAILURE;
        }

        $status = $releaseResponse->status();

        if ($status === 200) {
            $releaseData = $releaseResponse->json();
            $tagName = is_array($releaseData) && isset($releaseData['tag_name']) ? (string) $releaseData['tag_name'] : '-';
            $assets = is_array($releaseData) && isset($releaseData['assets']) && is_array($releaseData['assets']) ? count($releaseData['assets']) : 0;
            $publishedAt = is_array($releaseData) && isset($releaseData['published_at']) ? (string) $releaseData['published_at'] : '-';

            $this->info('   [BERHASIL] Rilis terbaru dapat diakses!');
            $this->line("   Tag rilis:    {$tagName}");
            $this->line("   Jumlah aset:  {$assets}");
            $this->line("   Dipublikasi:  {$publishedAt}");

            return self::SUCCESS;
        }

        if ($status === 404) {
            $acceptedScopes = (string) $releaseResponse->header('x-accepted-oauth-scopes');

            $this->error('   [GAGAL] Repositori atau rilis tidak ditemukan (HTTP 404).');
            $this->line('   Penyebab:');
            $this->line("   - Repositori '{$repository}' berstatus privat, dan token ini tidak memiliki scope 'repo'.");
            if ($acceptedScopes !== '') {
                $this->line("   - Scope yang disyaratkan GitHub: {$acceptedScopes}");
            }
            $this->line("   - Jika ini repositori organisasi, pastikan token telah diotorisasi untuk organisasi '{$this->organizationFromRepository($repository)}'.");
            $this->newLine();
            $this->line('   Solusi:');
            $this->line('   1. Buka https://github.com/settings/tokens');
            $this->line("   2. Buat token baru dengan mencentang scope 'repo'.");
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

        return $parts[0] ?? $repository;
    }
}
