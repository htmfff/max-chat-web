<?php

declare(strict_types=1);

namespace Tests\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Domain/Entity'],
            true,
        );

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }
}
