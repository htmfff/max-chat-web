<?php

declare(strict_types=1);

use App\Http\Controller\AuthController;
use App\Http\Controller\DocsController;
use App\Http\Controller\HealthController;
use App\Http\Controller\ImportController;
use App\Http\Controller\ProductController;
use Slim\App;

return static function (App $app): void {
    $app->get('/api/health', HealthController::class);

    $app->post('/api/auth/login', [AuthController::class, 'login']);
    $app->get('/api/auth/me', [AuthController::class, 'me']);

    $app->get('/api/products', [ProductController::class, 'index']);
    $app->get('/api/products/{id:[0-9]+}', [ProductController::class, 'show']);
    $app->post('/api/products', [ProductController::class, 'store']);
    $app->put('/api/products/{id:[0-9]+}', [ProductController::class, 'update']);
    $app->delete('/api/products/{id:[0-9]+}', [ProductController::class, 'destroy']);

    $app->get('/api/imports', [ImportController::class, 'index']);
    $app->post('/api/imports', [ImportController::class, 'store']);
    $app->get('/api/imports/{id:[0-9]+}', [ImportController::class, 'show']);

    $app->get('/api/docs', [DocsController::class, 'ui']);
    $app->get('/api/openapi.yaml', [DocsController::class, 'spec']);
};
