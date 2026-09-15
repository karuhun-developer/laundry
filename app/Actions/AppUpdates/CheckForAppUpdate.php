<?php

namespace App\Actions\AppUpdates;

use App\Services\AppUpdateService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckForAppUpdate
{
    public function __construct(private AppUpdateService $appUpdates) {}

    /**
     * @return array{current_version: string, current_changelog: list<string>, latest_version: string|null, latest_changelog: list<string>, update_available: bool, check_available: bool, unavailable_reason: string|null, download_url: string|null, apk_sha256: string|null, apk_filename: string|null, published_at: string|null}
     */
    public function handle(?string $currentVersion): array
    {
        try {
            return $this->appUpdates->check($currentVersion);
        } catch (Throwable $exception) {
            $this->logFailure($exception);

            return [
                'current_version' => $currentVersion ?? AppUpdateService::LEGACY_ANDROID_VERSION,
                'current_changelog' => [],
                'latest_version' => null,
                'latest_changelog' => [],
                'update_available' => false,
                'check_available' => false,
                'unavailable_reason' => 'Rilis terbaru belum dapat diperiksa. Coba lagi nanti.',
                'download_url' => null,
                'apk_sha256' => null,
                'apk_filename' => null,
                'published_at' => null,
            ];
        }
    }

    private function logFailure(Throwable $exception): void
    {
        try {
            Log::error('App update check failed unexpectedly.', [
                'exception' => $exception::class,
            ]);
        } catch (Throwable) {
        }
    }
}
