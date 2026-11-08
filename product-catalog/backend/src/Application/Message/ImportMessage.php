<?php

declare(strict_types=1);

namespace App\Application\Message;

final class ImportMessage
{
    public function __construct(public readonly int $jobId)
    {
    }
}
