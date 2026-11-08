<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Product;
use App\Domain\Entity\ProductAttribute;
use Doctrine\ORM\EntityManagerInterface;

final class ProductAttributeRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return list<ProductAttribute>
     */
    public function findByProduct(Product $product): array
    {
        /** @var list<ProductAttribute> $attributes */
        $attributes = $this->em->getRepository(ProductAttribute::class)
            ->findBy(['product' => $product], ['id' => 'ASC']);

        return $attributes;
    }
}
