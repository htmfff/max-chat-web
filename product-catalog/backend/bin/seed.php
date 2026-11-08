<?php

declare(strict_types=1);

use App\Application\Service\DiscountCalculator;
use App\Infrastructure\Persistence\Fixture\ProductFixtures;
use App\Infrastructure\Persistence\Fixture\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;

$container = require dirname(__DIR__) . '/config/bootstrap.php';

/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);

(new ProductFixtures($em, new DiscountCalculator()))->load();
(new UserFixtures($em))->load();

fwrite(STDOUT, "Fixtures loaded.\n");
