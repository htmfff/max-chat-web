<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Service\ImageDownloaderInterface;

final class FakeImageDownloader implements ImageDownloaderInterface
{
    /** @var list<string> */
    public array $downloaded = [];

    public function download(string $url, string $externalCode, int $position): ?string
    {
        $this->downloaded[] = $url;

        return sprintf('/uploads/fake/%s-%02d.jpg', $externalCode, $position);
    }
}
