<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\UnauthorizedException;
use App\Domain\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

final class JsonErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return json_response(new Response(), [
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (NotFoundException $e) {
            return json_response(new Response(), [
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], 404);
        } catch (UnauthorizedException $e) {
            return json_response(new Response(), [
                'error' => 'unauthorized',
                'message' => $e->getMessage(),
            ], 401);
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled API error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return json_response(new Response(), [
                'error' => 'internal_error',
                'message' => 'Внутренняя ошибка сервера.',
                'debug' => $this->debug ? $e->getMessage() : null,
            ], 500);
        }
    }
}
