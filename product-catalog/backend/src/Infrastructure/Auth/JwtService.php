<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Entity\User;
use App\Domain\Exception\UnauthorizedException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => 'product-catalog',
            'sub' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ], $this->secret, 'HS256');
    }

    /**
     * @return array{sub: string, email: string, roles: list<string>, exp: int}
     */
    public function decode(string $token): array
    {
        try {
            /** @var stdClass $decoded */
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable $e) {
            throw new UnauthorizedException('Токен недействителен или истёк.', 0, $e);
        }

        /** @var array{sub: string, email: string, roles: list<string>, exp: int} $payload */
        $payload = (array) $decoded;

        return $payload;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
