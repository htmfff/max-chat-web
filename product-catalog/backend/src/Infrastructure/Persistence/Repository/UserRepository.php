<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(['email' => mb_strtolower($email)]);
    }

    public function findById(int $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->em->persist($user);

        if ($flush) {
            $this->em->flush();
        }
    }
}
