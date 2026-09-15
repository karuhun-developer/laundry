<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class AppUpdateService
{
    public const LEGACY_ANDROID_VERSION = '1.0.13';

    private const APK_SIZE_LIMIT_BYTES = 104_857_600;

    /**
     * @return array{current_version: string, current_changelog: list<string>, latest_version: string|null, latest_changelog: list<string>, update_available: bool, check_available: bool, unavailable_reason: string|null, download_url: string|null, apk_sha256: string|null, apk_filename: string|null, published_at: string|null}
     */
    public function check(?string $currentVersion): array
    {
        $installedVersion = $currentVersion ?? $this->latestLocalVersion();
        $base = [
            'current_version' => $installedVersion,
            'current_changelog' => $this->changelogFor($installedVersion),
            'latest_version' => null,
            'latest_changelog' => [],
            'update_available' => false,
            'check_available' => false,
            'unavailable_reason' => null,
            'download_url' => null,
            'apk_sha256' => null,
            'apk_filename' => null,
            'published_at' => null,
        ];

        if (! $this->isConfigured()) {
            return [...$base, 'unavailable_reason' => 'Pengecekan pembaruan belum dikonfigurasi di server.'];
        }

        try {
            $release = $this->githubRequest()
                ->get($this->repositoryApiUrl('/releases/latest'))
                ->throw()
                ->json();

            if (! is_array($release)) {
                throw new RuntimeException('Respons rilis GitHub tidak valid.');
            }

            $latestVersion = $this->releaseVersion($release);
            $result = [
                ...$base,
                'latest_version' => $latestVersion,
                'latest_changelog' => $this->releaseNotes($release, $latestVersion),
                'update_available' => version_compare($latestVersion, $installedVersion, '>'),
                'check_available' => true,
                'published_at' => is_string($release['published_at'] ?? null)
                    ? $release['published_at']
                    : null,
            ];

            if (! $result['update_available']) {
                return $result;
            }

            $filename = "laundryhub-v{$latestVersion}.apk";
            $asset = $this->findAsset($release, $filename);

            if ($asset === null) {
                return [
                    ...$result,
                    'check_available' => false,
                    'unavailable_reason' => 'Rilis terbaru belum memiliki berkas APK yang dapat dipasang.',
                ];
            }

            $assetSize = $asset['size'] ?? null;
            if (is_int($assetSize) && $assetSize > self::APK_SIZE_LIMIT_BYTES) {
                return [
                    ...$result,
                    'check_available' => false,
                    'unavailable_reason' => 'Ukuran APK terbaru melebihi batas unduhan aplikasi.',
                ];
            }

            $checksum = $this->assetChecksum($release, $asset, $filename);
            if ($checksum === null) {
                return [
                    ...$result,
                    'check_available' => false,
                    'unavailable_reason' => 'Checksum APK terbaru belum tersedia.',
                ];
            }

            $assetId = $asset['id'] ?? null;
            if (! is_int($assetId)) {
                throw new RuntimeException('ID aset APK tidak valid.');
            }

            return [
                ...$result,
                'download_url' => URL::temporarySignedRoute(
                    'app-updates.download',
                    now()->addMinutes(15),
                    ['asset' => $assetId, 'filename' => $filename],
                ),
                'apk_sha256' => $checksum,
                'apk_filename' => $filename,
            ];
        } catch (Throwable) {
            return [
                ...$base,
                'unavailable_reason' => 'Rilis terbaru belum dapat diperiksa. Periksa koneksi lalu coba lagi.',
            ];
        }
    }

    public function downloadAsset(int $assetId): Response
    {
        if (! $this->isConfigured()) {
            abort(503, 'Pembaruan aplikasi belum dikonfigurasi.');
        }

        return $this->githubRequest()
            ->withOptions(['stream' => true])
            ->accept('application/octet-stream')
            ->get($this->repositoryApiUrl("/releases/assets/{$assetId}"))
            ->throw();
    }

    public function latestLocalVersion(): string
    {
        $contents = $this->changelogContents();

        if (preg_match('/^## \[([0-9]+\.[0-9]+\.[0-9]+)\] - \d{4}-\d{2}-\d{2}$/m', $contents, $matches) !== 1) {
            return self::LEGACY_ANDROID_VERSION;
        }

        return $matches[1];
    }

    /** @return list<string> */
    public function changelogFor(string $version): array
    {
        $quotedVersion = preg_quote($version, '/');
        $pattern = '/^## \['.$quotedVersion.'\] - \d{4}-\d{2}-\d{2}\R(?<body>.*?)(?=^## |\z)/ms';

        if (preg_match($pattern, $this->changelogContents(), $matches) !== 1) {
            return [];
        }

        return $this->bulletLines($matches['body']);
    }

    private function changelogContents(): string
    {
        $contents = file_get_contents(base_path('CHANGELOG.md'));

        return is_string($contents) ? $contents : '';
    }

    private function isConfigured(): bool
    {
        return $this->repository() !== '' && $this->token() !== '';
    }

    private function repository(): string
    {
        $repository = config('services.github_releases.repository');

        return is_string($repository) && preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) === 1
            ? $repository
            : '';
    }

    private function token(): string
    {
        $token = config('services.github_releases.token');

        return is_string($token) ? trim($token) : '';
    }

    private function githubRequest(): PendingRequest
    {
        return Http::withToken($this->token())
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->connectTimeout(5)
            ->timeout(30);
    }

    private function repositoryApiUrl(string $path): string
    {
        return 'https://api.github.com/repos/'.$this->repository().$path;
    }

    /** @param array<string, mixed> $release */
    private function releaseVersion(array $release): string
    {
        $tagName = $release['tag_name'] ?? null;
        $version = is_string($tagName) ? ltrim($tagName, 'v') : '';

        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) !== 1) {
            throw new RuntimeException('Versi rilis GitHub tidak valid.');
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>  $release
     * @return list<string>
     */
    private function releaseNotes(array $release, string $version): array
    {
        $body = $release['body'] ?? null;
        $notes = is_string($body) ? $this->bulletLines($body) : [];

        return $notes !== [] ? $notes : $this->changelogFor($version);
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>|null
     */
    private function findAsset(array $release, string $filename): ?array
    {
        $assets = $release['assets'] ?? null;
        if (! is_array($assets)) {
            return null;
        }

        foreach ($assets as $asset) {
            if (is_array($asset) && ($asset['name'] ?? null) === $filename) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $release
     * @param  array<string, mixed>  $apkAsset
     */
    private function assetChecksum(array $release, array $apkAsset, string $filename): ?string
    {
        $digest = $apkAsset['digest'] ?? null;
        if (is_string($digest) && preg_match('/^sha256:([a-f0-9]{64})$/i', $digest, $matches) === 1) {
            return strtolower($matches[1]);
        }

        $checksumAsset = $this->findAsset($release, 'SHA256SUMS.txt');
        $checksumAssetId = $checksumAsset['id'] ?? null;
        if (! is_int($checksumAssetId)) {
            return null;
        }

        $contents = $this->githubRequest()
            ->accept('application/octet-stream')
            ->get($this->repositoryApiUrl("/releases/assets/{$checksumAssetId}"))
            ->throw()
            ->body();
        $quotedFilename = preg_quote($filename, '/');

        if (preg_match('/^([a-f0-9]{64})\s+\*?'.$quotedFilename.'$/mi', $contents, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /** @return list<string> */
    private function bulletLines(string $contents): array
    {
        preg_match_all('/^-\s+(.+)$/m', $contents, $matches);

        return array_map(
            static fn (string $line): string => trim($line),
            $matches[1],
        );
    }
}
