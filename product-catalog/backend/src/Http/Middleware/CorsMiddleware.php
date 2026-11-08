<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $allowOrigin)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(204);
        } else {
            $response = $handler->handle($request);
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Expose-Headers', 'X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');
    }
}
