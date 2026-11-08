<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/vendor/autoload.php';

if (is_file(BASE_PATH . '/.env')) {
    Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

$builder = new ContainerBuilder();
$builder->addDefinitions(BASE_PATH . '/config/container.php');

return $builder->build();
