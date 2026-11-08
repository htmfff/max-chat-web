<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DocsController
{
    public function __construct(private readonly string $specPath)
    {
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Product Catalog API — Swagger UI</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script>
  window.ui = SwaggerUIBundle({
    url: '/api/openapi.yaml',
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis]
  });
</script>
</body>
</html>
HTML;

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write((string) file_get_contents($this->specPath));

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }
}
