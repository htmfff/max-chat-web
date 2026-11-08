<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Fixture;

use App\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserFixtures
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function load(): void
    {
        $email = mb_strtolower((string) env('SEED_ADMIN_EMAIL', 'admin@example.com'));
        $password = (string) env('SEED_ADMIN_PASSWORD', 'admin123');

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existing !== null) {
            return;
        }

        $user = new User(
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            ['ROLE_ADMIN', 'ROLE_USER'],
        );

        $this->em->persist($user);
        $this->em->flush();
    }
}
