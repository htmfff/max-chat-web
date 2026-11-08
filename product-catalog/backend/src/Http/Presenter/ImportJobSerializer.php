<?php

declare(strict_types=1);

namespace App\Http\Presenter;

use App\Domain\Entity\ImportJob;

final class ImportJobSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(ImportJob $job): array
    {
        return [
            'id' => $job->getId(),
            'uuid' => $job->getUuid(),
            'status' => $job->getStatus(),
            'originalFilename' => $job->getOriginalFilename(),
            'totalRows' => $job->getTotalRows(),
            'processedRows' => $job->getProcessedRows(),
            'successRows' => $job->getSuccessRows(),
            'errorRows' => $job->getErrorRows(),
            'progress' => $job->getProgressPercent(),
            'errors' => $job->getErrors(),
            'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
        ];
    }
}
