<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Service\DiscountCalculator;
use App\Application\Service\ProductService;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Persistence\Repository\ProductRepository;
use Tests\Support\DatabaseTestCase;

final class ProductCrudTest extends DatabaseTestCase
{
    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductService(
            $this->em,
            new ProductRepository($this->em),
            new DiscountCalculator(),
        );
    }

    public function testCreatesReadsUpdatesAndDeletesProduct(): void
    {
        $created = $this->service->create([
            'externalCode' => 'CRUD-1',
            'name' => 'Test product',
            'description' => 'Description',
            'price' => '1000',
            'purchasePrice' => '750',
            'attributes' => [
                ['key' => 'Color', 'value' => 'Red'],
            ],
            'images' => [
                ['url' => 'https://example.com/image.jpg'],
            ],
        ]);

        self::assertNotNull($created->getId());
        self::assertSame('25.00', $created->getDiscount());
        self::assertCount(1, $created->getAttributes());
        self::assertCount(1, $created->getImages());

        $found = $this->service->get((int) $created->getId());
        self::assertSame('CRUD-1', $found->getExternalCode());

        $updated = $this->service->update((int) $created->getId(), [
            'name' => 'Updated product',
            'price' => '2000',
            'attributes' => [
                ['key' => 'Color', 'value' => 'Blue'],
                ['key' => 'Size', 'value' => 'XL'],
            ],
        ]);

        self::assertSame('Updated product', $updated->getName());
        self::assertSame('62.50', $updated->getDiscount());
        self::assertCount(2, $updated->getAttributes());

        $this->service->delete((int) $created->getId());

        $this->expectException(NotFoundException::class);
        $this->service->get((int) $created->getId());
    }

    public function testRejectsDuplicateExternalCode(): void
    {
        $this->service->create([
            'externalCode' => 'DUP-1',
            'name' => 'First',
            'price' => '100',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create([
            'externalCode' => 'DUP-1',
            'name' => 'Second',
            'price' => '200',
        ]);
    }

    public function testRejectsInvalidPrice(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'externalCode' => 'BAD-PRICE',
            'name' => 'Bad price',
            'price' => '0',
        ]);
    }
}
