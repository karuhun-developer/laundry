<?php

namespace App\Actions\AppUpdates;

use App\Services\AppUpdateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAppUpdate
{
    public function __construct(private AppUpdateService $appUpdates) {}

    public function handle(int $assetId, string $filename): StreamedResponse
    {
        abort_unless(preg_match('/^laundryhub-v[0-9]+\.[0-9]+\.[0-9]+\.apk$/', $filename) === 1, 404);

        $githubResponse = $this->appUpdates->downloadAsset($assetId);

        return response()->streamDownload(
            static function () use ($githubResponse): void {
                $stream = $githubResponse->resource();

                if (is_resource($stream)) {
                    fpassthru($stream);
                }
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
