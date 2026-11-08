<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Application\Service\ImageDownloaderInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

final class ImageDownloader implements ImageDownloaderInterface
{
    private const MAX_BYTES = 10_485_760;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $publicDir,
    ) {
    }

    public function download(string $url, string $externalCode, int $position): ?string
    {
        $url = trim($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 15,
                'connect_timeout' => 5,
                'http_errors' => false,
                'allow_redirects' => ['max' => 3],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Image download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('Image download returned non-200 status', [
                'url' => $url,
                'status' => $response->getStatusCode(),
            ]);

            return null;
        }

        $body = $response->getBody()->getContents();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $extension = $this->detectExtension($response->getHeaderLine('Content-Type'), $body, $url);

        if ($extension === null) {
            $this->logger->warning('Unsupported image content type', ['url' => $url]);

            return null;
        }

        $safeCode = preg_replace('/[^A-Za-z0-9._-]+/', '-', $externalCode) ?: 'product';
        $directory = $this->publicDir . '/uploads/products/' . $safeCode;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->logger->error('Cannot create image directory', ['directory' => $directory]);

            return null;
        }

        $filename = sprintf('%02d-%s.%s', $position, substr(sha1($url), 0, 12), $extension);

        if (file_put_contents($directory . '/' . $filename, $body) === false) {
            return null;
        }

        return '/uploads/products/' . $safeCode . '/' . $filename;
    }

    private function detectExtension(string $contentType, string $body, string $url): ?string
    {
        $mime = mb_strtolower(trim(explode(';', $contentType)[0]));

        if (isset(self::MIME_EXTENSIONS[$mime])) {
            return self::MIME_EXTENSIONS[$mime];
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $detected = is_string($detected) ? mb_strtolower($detected) : '';

        if (isset(self::MIME_EXTENSIONS[$detected])) {
            return self::MIME_EXTENSIONS[$detected];
        }

        $pathExtension = mb_strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return in_array($pathExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)
            ? ($pathExtension === 'jpeg' ? 'jpg' : $pathExtension)
            : null;
    }
}
