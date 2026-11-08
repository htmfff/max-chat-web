<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Exception\UnauthorizedException;
use App\Infrastructure\Auth\JwtService;
use App\Infrastructure\Persistence\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly JwtService $jwt,
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (array) ($request->getParsedBody() ?? []);
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            return json_response($response, [
                'error' => 'validation_error',
                'message' => 'Email и пароль обязательны.',
            ], 422);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user->getPasswordHash())) {
            throw new UnauthorizedException('Неверный email или пароль.');
        }

        return json_response($response, [
            'token' => $this->jwt->issue($user),
            'expiresIn' => $this->jwt->getTtl(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var array{sub: string}|null $auth */
        $auth = $request->getAttribute('auth');

        if ($auth === null) {
            throw new UnauthorizedException('Пользователь не аутентифицирован.');
        }

        $user = $this->users->findById((int) $auth['sub']);

        if ($user === null) {
            throw new UnauthorizedException('Пользователь не найден.');
        }

        return json_response($response, [
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
