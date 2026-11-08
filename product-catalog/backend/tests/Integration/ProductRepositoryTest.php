<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Entity\Product;
use App\Infrastructure\Persistence\Repository\ProductRepository;
use Tests\Support\DatabaseTestCase;

final class ProductRepositoryTest extends DatabaseTestCase
{
    public function testPaginatesAndFiltersProducts(): void
    {
        $this->createProduct('A-1', 'Alpha trousers', '100.00');
        $this->createProduct('A-2', 'Beta jacket', '250.00');
        $this->createProduct('A-3', 'Gamma socks', '400.00');

        $repository = new ProductRepository($this->em);

        $page = $repository->paginate(1, 2);
        self::assertCount(2, $page['items']);
        self::assertSame(3, $page['total']);

        $filtered = $repository->paginate(1, 20, 'jacket');
        self::assertCount(1, $filtered['items']);
        self::assertSame('Beta jacket', $filtered['items'][0]->getName());

        $range = $repository->paginate(1, 20, null, 150.0, 500.0);
        self::assertCount(2, $range['items']);

        $sorted = $repository->paginate(1, 20, null, null, null, 'price', 'desc');
        self::assertSame('Gamma socks', $sorted['items'][0]->getName());
    }

    public function testFindsProductByExternalCode(): void
    {
        $this->createProduct('UNIQUE-CODE', 'Unique product', '10.00');

        $repository = new ProductRepository($this->em);
        $product = $repository->findByExternalCode('UNIQUE-CODE');

        self::assertInstanceOf(Product::class, $product);
        self::assertSame('Unique product', $product->getName());
    }

    private function createProduct(string $externalCode, string $name, string $price): void
    {
        $this->em->persist(new Product($externalCode, $name, $price));
        $this->em->flush();
    }
}
