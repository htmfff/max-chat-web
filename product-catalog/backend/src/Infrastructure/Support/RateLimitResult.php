<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $retryAfter,
    ) {
    }
}
