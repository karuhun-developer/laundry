<?php

namespace App\Services\Qris;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrisImageGenerator
{
    public function dataUri(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(size: 360, margin: 4),
            new SvgImageBackEnd,
        );
        $svg = (new Writer($renderer))->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
