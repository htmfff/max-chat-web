<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Support\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $limit,
        private readonly int $windowSeconds,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST' || !str_starts_with($request->getUri()->getPath(), '/api/imports')) {
            return $handler->handle($request);
        }

        $serverParams = $request->getServerParams();
        $ip = (string) ($serverParams['REMOTE_ADDR'] ?? 'unknown');
        $result = $this->limiter->consume('import:' . $ip, $this->limit, $this->windowSeconds);

        if (!$result->allowed) {
            return json_response(new Response(), [
                'error' => 'rate_limited',
                'message' => 'Слишком много запросов на импорт. Попробуйте позже.',
                'retryAfter' => $result->retryAfter,
            ], 429, ['Retry-After' => (string) $result->retryAfter]);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $result->remaining);
    }
}
