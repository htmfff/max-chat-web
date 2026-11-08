<?php

declare(strict_types=1);

namespace App\Application\Service;

final class ImportReport
{
    /**
     * @param list<array{row: int, message: string, externalCode?: string|null}> $errors
     */
    public function __construct(
        public readonly int $total,
        public readonly int $success,
        public readonly array $errors,
    ) {
    }
}
