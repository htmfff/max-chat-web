<?php

declare(strict_types=1);

namespace App\Application\MessageHandler;

use App\Application\Message\ImportMessage;
use App\Application\Service\ProductImportService;
use App\Infrastructure\Persistence\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ImportProductsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportJobRepository $jobs,
        private readonly ProductImportService $importService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportMessage $message): void
    {
        $job = $this->jobs->findById($message->jobId);

        if ($job === null) {
            $this->logger->error('Import job not found', ['jobId' => $message->jobId]);

            return;
        }

        $job->markProcessing();
        $this->em->flush();

        try {
            $report = $this->importService->import(
                $job->getStoredPath(),
                function (int $processed, int $total, int $success, array $errors) use ($job): void {
                    $job->updateProgress($processed, $total, $success, $errors);
                    $this->em->flush();
                },
            );

            $job->markCompleted($report->total, $report->total, $report->success, $report->errors);
            $this->em->flush();

            $this->logger->info('Import finished', [
                'jobId' => $message->jobId,
                'total' => $report->total,
                'success' => $report->success,
                'errors' => count($report->errors),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Import job failed', [
                'jobId' => $message->jobId,
                'error' => $e->getMessage(),
            ]);

            if ($this->em->isOpen()) {
                $job->markFailed($e->getMessage());
                $this->em->flush();
            }
        }
    }
}
