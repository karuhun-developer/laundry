<?php

namespace App\Http\Controllers;

use App\Actions\AppUpdates\DownloadAppUpdate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppUpdateDownloadController extends Controller
{
    public function __invoke(
        int $asset,
        string $filename,
        DownloadAppUpdate $downloadAppUpdate,
    ): StreamedResponse {
        return $downloadAppUpdate->handle($asset, $filename);
    }
}
