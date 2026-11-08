<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Product;
use App\Domain\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;

final class ProductImageRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return list<ProductImage>
     */
    public function findByProduct(Product $product): array
    {
        /** @var list<ProductImage> $images */
        $images = $this->em->getRepository(ProductImage::class)
            ->findBy(['product' => $product], ['position' => 'ASC', 'id' => 'ASC']);

        return $images;
    }
}
