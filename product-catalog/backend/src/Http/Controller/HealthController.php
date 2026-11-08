<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $database = 'ok';

        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $database = 'error';
        }

        $healthy = $database === 'ok';

        return json_response($response, [
            'status' => $healthy ? 'ok' : 'degraded',
            'database' => $database,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'version' => '1.0.0',
        ], $healthy ? 200 : 503);
    }
}
