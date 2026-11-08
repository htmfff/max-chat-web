<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Exception\UnauthorizedException;
use App\Infrastructure\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    private const PUBLIC_PATHS = [
        '/api/health',
        '/api/auth/login',
        '/api/docs',
        '/api/openapi.yaml',
    ];

    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';

        if ($request->getMethod() === 'OPTIONS' || in_array($path, self::PUBLIC_PATHS, true)) {
            return $handler->handle($request);
        }

        $header = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return json_response(new Response(), [
                'error' => 'unauthorized',
                'message' => 'Требуется заголовок Authorization: Bearer <token>.',
            ], 401);
        }

        try {
            $payload = $this->jwt->decode(trim($matches[1]));
        } catch (UnauthorizedException $e) {
            return json_response(new Response(), [
                'error' => 'unauthorized',
                'message' => $e->getMessage(),
            ], 401);
        }

        return $handler->handle($request->withAttribute('auth', $payload));
    }
}
