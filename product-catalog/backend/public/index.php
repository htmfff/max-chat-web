<?php

declare(strict_types=1);

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\JsonErrorMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Slim\Factory\AppFactory;

$container = require dirname(__DIR__) . '/config/bootstrap.php';

$app = AppFactory::createFromContainer($container);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

(require dirname(__DIR__) . '/config/routes.php')($app);

// Middleware is executed in reverse order: error -> CORS -> auth -> rate limit -> route.
$app->add($container->get(RateLimitMiddleware::class));
$app->add($container->get(AuthMiddleware::class));
$app->add($container->get(CorsMiddleware::class));
$app->add($container->get(JsonErrorMiddleware::class));

$app->run();
