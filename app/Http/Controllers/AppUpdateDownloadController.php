<?php

namespace App\Http\Controllers;

use App\Services\AppUpdateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppUpdateDownloadController extends Controller
{
    public function __invoke(int $asset, string $filename, AppUpdateService $appUpdates): StreamedResponse
    {
        abort_unless(preg_match('/^laundryhub-v[0-9]+\.[0-9]+\.[0-9]+\.apk$/', $filename) === 1, 404);

        $githubResponse = $appUpdates->downloadAsset($asset);

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
