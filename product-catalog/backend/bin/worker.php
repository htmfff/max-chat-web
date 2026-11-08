<?php

declare(strict_types=1);

use App\Application\Message\ImportMessage;
use App\Application\MessageHandler\ImportProductsHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;

$container = require dirname(__DIR__) . '/config/bootstrap.php';

$dsn = (string) env('MESSENGER_TRANSPORT_DSN', 'sync://');

if (!str_starts_with($dsn, 'amqp://')) {
    fwrite(STDERR, "Worker requires an amqp:// MESSENGER_TRANSPORT_DSN, got: {$dsn}\n");
    exit(1);
}

$transport = (new AmqpTransportFactory())->createTransport($dsn, [], new PhpSerializer());

/** @var ImportProductsHandler $handler */
$handler = $container->get(ImportProductsHandler::class);

$bus = new MessageBus([
    new HandleMessageMiddleware(new HandlersLocator([
        ImportMessage::class => [$handler],
    ])),
]);

/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

$worker = new Worker(
    ['async' => $transport],
    $bus,
    new EventDispatcher(),
    $logger,
);

fwrite(STDOUT, "Import worker started, waiting for messages...\n");

$worker->run([
    'sleep' => 1_000_000,
]);
