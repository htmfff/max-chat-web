<?php

declare(strict_types=1);

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;

$container = require dirname(__DIR__) . '/config/bootstrap.php';

/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);

$configuration = new Configuration($em->getConnection());
$configuration->setMigrationsDirectory(dirname(__DIR__) . '/migrations');
$configuration->setMigrationsNamespace('Migrations');
$configuration->setMigrationsTableName('doctrine_migration_versions');
$configuration->setAllOrNothing(true);
$configuration->setCheckDatabasePlatform(false);

$dependencyFactory = new DependencyFactory($configuration);
$dependencyFactory->getMigrator()->migrate();

fwrite(STDOUT, "Migrations applied.\n");
