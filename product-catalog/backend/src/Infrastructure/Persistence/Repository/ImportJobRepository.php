<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\ImportJob;
use Doctrine\ORM\EntityManagerInterface;

final class ImportJobRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    public function findByUuid(string $uuid): ?ImportJob
    {
        return $this->em->getRepository(ImportJob::class)
            ->findOneBy(['uuid' => $uuid]);
    }

    public function save(ImportJob $job, bool $flush = true): void
    {
        $this->em->persist($job);

        if ($flush) {
            $this->em->flush();
        }
    }

    /**
     * @return list<ImportJob>
     */
    public function findLatest(int $limit = 10): array
    {
        /** @var list<ImportJob> $jobs */
        $jobs = $this->em->createQueryBuilder()
            ->select('j')
            ->from(ImportJob::class, 'j')
            ->orderBy('j.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $jobs;
    }
}
