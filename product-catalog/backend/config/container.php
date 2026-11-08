<?php

declare(strict_types=1);

use App\Application\Message\ImportMessage;
use App\Application\MessageHandler\ImportProductsHandler;
use App\Application\Service\DiscountCalculator;
use App\Application\Service\ImageDownloaderInterface;
use App\Application\Service\ImportDispatcher;
use App\Application\Service\ProductImportService;
use App\Application\Service\ProductService;
use App\Http\Controller\DocsController;
use App\Http\Controller\ImportController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\JsonErrorMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Presenter\ImportJobSerializer;
use App\Http\Presenter\ProductSerializer;
use App\Infrastructure\Auth\JwtService;
use App\Infrastructure\Import\ImageDownloader;
use App\Infrastructure\Import\UploadedFileValidator;
use App\Infrastructure\Import\XlsxParser;
use App\Infrastructure\Support\RateLimiter;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

return [
    EntityManagerInterface::class => static function (): EntityManagerInterface {
        $isDev = env('APP_ENV', 'dev') !== 'prod';
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [BASE_PATH . '/src/Domain/Entity'],
            $isDev,
            BASE_PATH . '/var/cache/doctrine',
        );
        $config->setProxyDir(BASE_PATH . '/var/cache/doctrine');

        $connection = DriverManager::getConnection([
            'url' => (string) env('DATABASE_URL', 'sqlite:///' . BASE_PATH . '/var/data.sqlite'),
        ], $config);

        return new EntityManager($connection, $config);
    },

    LoggerInterface::class => static function (): LoggerInterface {
        $logger = new Logger('app');
        $level = env('APP_ENV', 'dev') === 'dev' ? Logger::DEBUG : Logger::INFO;
        $logger->pushHandler(new StreamHandler('php://stderr', $level));

        return $logger;
    },

    ClientInterface::class => static fn (): ClientInterface => new Client([
        'headers' => ['User-Agent' => 'ProductCatalogImporter/1.0'],
    ]),

    ImageDownloaderInterface::class => static fn (ContainerInterface $c): ImageDownloaderInterface => new ImageDownloader(
        $c->get(ClientInterface::class),
        $c->get(LoggerInterface::class),
        BASE_PATH . '/public',
    ),

    DiscountCalculator::class => static fn (): DiscountCalculator => new DiscountCalculator(),
    XlsxParser::class => static fn (): XlsxParser => new XlsxParser(),
    ProductSerializer::class => static fn (): ProductSerializer => new ProductSerializer(),
    ImportJobSerializer::class => static fn (): ImportJobSerializer => new ImportJobSerializer(),
    ProductService::class => static fn (ContainerInterface $c): ProductService => new ProductService(
        $c->get(EntityManagerInterface::class),
        $c->get(App\Infrastructure\Persistence\Repository\ProductRepository::class),
        $c->get(DiscountCalculator::class),
    ),

    ProductImportService::class => static fn (ContainerInterface $c): ProductImportService => new ProductImportService(
        $c->get(EntityManagerInterface::class),
        $c->get(App\Infrastructure\Persistence\Repository\ProductRepository::class),
        $c->get(ImageDownloaderInterface::class),
        $c->get(DiscountCalculator::class),
        $c->get(XlsxParser::class),
        $c->get(LoggerInterface::class),
    ),

    JwtService::class => static fn (): JwtService => new JwtService(
        (string) env('JWT_SECRET', 'change-me-in-production'),
        env_int('JWT_TTL', 28800),
    ),

    UploadedFileValidator::class => static fn (): UploadedFileValidator => new UploadedFileValidator(
        env_int('IMPORT_MAX_FILE_SIZE', 10_485_760),
    ),

    ImportController::class => static fn (ContainerInterface $c): ImportController => new ImportController(
        $c->get(App\Infrastructure\Persistence\Repository\ImportJobRepository::class),
        $c->get(ImportDispatcher::class),
        $c->get(UploadedFileValidator::class),
        $c->get(ImportJobSerializer::class),
        BASE_PATH . '/var/uploads',
    ),

    DocsController::class => static fn (): DocsController => new DocsController(BASE_PATH . '/docs/openapi.yaml'),

    RateLimiter::class => static fn (): RateLimiter => new RateLimiter(BASE_PATH . '/var/cache/ratelimit'),
    RateLimitMiddleware::class => static fn (ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware(
        $c->get(RateLimiter::class),
        env_int('IMPORT_RATE_LIMIT', 5),
        env_int('IMPORT_RATE_WINDOW', 60),
    ),
    CorsMiddleware::class => static fn (): CorsMiddleware => new CorsMiddleware(
        (string) env('CORS_ALLOW_ORIGIN', '*'),
    ),
    JsonErrorMiddleware::class => static fn (ContainerInterface $c): JsonErrorMiddleware => new JsonErrorMiddleware(
        $c->get(LoggerInterface::class),
        env_bool('APP_DEBUG', true),
    ),
    AuthMiddleware::class => static fn (ContainerInterface $c): AuthMiddleware => new AuthMiddleware(
        $c->get(JwtService::class),
    ),

    MessageBusInterface::class => static function (ContainerInterface $c): MessageBusInterface {
        $dsn = (string) env('MESSENGER_TRANSPORT_DSN', 'sync://');

        if (str_starts_with($dsn, 'sync://')) {
            return new MessageBus([
                new HandleMessageMiddleware(new HandlersLocator([
                    ImportMessage::class => [$c->get(ImportProductsHandler::class)],
                ])),
            ]);
        }

        $transport = (new AmqpTransportFactory())->createTransport(
            $dsn,
            [],
            new PhpSerializer(),
        );

        return new MessageBus([
            new SendMessageMiddleware(new SendersLocator([
                ImportMessage::class => ['async'],
            ], ['async' => $transport])),
        ]);
    },

    ImportDispatcher::class => static fn (ContainerInterface $c): ImportDispatcher => new ImportDispatcher(
        $c->get(MessageBusInterface::class),
    ),
];
