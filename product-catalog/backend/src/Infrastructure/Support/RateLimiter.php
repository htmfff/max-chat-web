<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

final class RateLimiter
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function consume(string $key, int $limit, int $windowSeconds): RateLimitResult
    {
        $file = $this->directory . '/' . hash('sha256', $key) . '.json';
        $handle = fopen($file, 'c+');

        if ($handle === false) {
            // Never block imports because of a filesystem issue.
            return new RateLimitResult(true, $limit, 0);
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $now = time();
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            if (!is_array($data) || !isset($data['reset'], $data['count']) || (int) $data['reset'] <= $now) {
                $data = ['count' => 0, 'reset' => $now + $windowSeconds];
            }

            ++$data['count'];

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($data));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $allowed = $data['count'] <= $limit;

        return new RateLimitResult(
            $allowed,
            max(0, $limit - (int) $data['count']),
            max(0, (int) $data['reset'] - $now),
        );
    }
}
