<?php

declare(strict_types=1);

namespace App\Application\Service;

interface ImageDownloaderInterface
{
    /**
     * Downloads an image and returns the public path (e.g. /uploads/products/code/01-abc.jpg).
     * Returns null when the image could not be downloaded.
     */
    public function download(string $url, string $externalCode, int $position): ?string;
}
