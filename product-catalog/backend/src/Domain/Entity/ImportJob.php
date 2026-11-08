<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
#[ORM\HasLifecycleCallbacks]
class ImportJob
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(name: 'original_filename', type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'stored_path', type: 'string', length: 500)]
    private string $storedPath;

    #[ORM\Column(name: 'total_rows', type: 'integer')]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows', type: 'integer')]
    private int $processedRows = 0;

    #[ORM\Column(name: 'success_rows', type: 'integer')]
    private int $successRows = 0;

    #[ORM\Column(name: 'error_rows', type: 'integer')]
    private int $errorRows = 0;

    /** @var list<array{row: int, message: string, externalCode?: string|null}> */
    #[ORM\Column(type: 'json')]
    private array $errors = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(string $uuid, string $originalFilename, string $storedPath)
    {
        $this->uuid = $uuid;
        $this->originalFilename = $originalFilename;
        $this->storedPath = $storedPath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Doctrine hydrates the identifier through reflection; this setter also
     * lets fixtures and tests create deterministic records.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoredPath(): string
    {
        return $this->storedPath;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getSuccessRows(): int
    {
        return $this->successRows;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }

    /** @return list<array{row: int, message: string, externalCode?: string|null}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->startedAt = new \DateTimeImmutable();
    }

    /**
     * @param list<array{row: int, message: string, externalCode?: string|null}> $errors
     */
    public function updateProgress(int $processed, int $total, int $success, array $errors): void
    {
        $this->processedRows = $processed;
        $this->totalRows = $total;
        $this->successRows = $success;
        $this->errorRows = count($errors);
        $this->errors = $errors;
    }

    /**
     * @param list<array{row: int, message: string, externalCode?: string|null}> $errors
     */
    public function markCompleted(int $processed, int $total, int $success, array $errors): void
    {
        $this->updateProgress($processed, $total, $success, $errors);
        $this->status = self::STATUS_COMPLETED;
        $this->finishedAt = new \DateTimeImmutable();
    }

    /**
     * @param list<array{row: int, message: string, externalCode?: string|null}> $errors
     */
    public function markFailed(string $message, array $errors = []): void
    {
        if ($errors === []) {
            $errors = [['row' => 0, 'message' => $message]];
        }

        $this->errors = $errors;
        $this->errorRows = count($errors);
        $this->status = self::STATUS_FAILED;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function getProgressPercent(): int
    {
        if ($this->totalRows <= 0) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 0;
        }

        return (int) min(100, round($this->processedRows / $this->totalRows * 100));
    }
}
