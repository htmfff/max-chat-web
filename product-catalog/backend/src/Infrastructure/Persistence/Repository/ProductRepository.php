<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final class ProductRepository
{
    private const SORTABLE = [
        'id' => 'p.id',
        'name' => 'p.name',
        'price' => 'p.price',
        'discount' => 'p.discount',
        'createdAt' => 'p.createdAt',
        'updatedAt' => 'p.updatedAt',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $name = null,
        ?float $priceFrom = null,
        ?float $priceTo = null,
        string $sort = 'id',
        string $direction = 'asc',
    ): array {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p');

        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . mb_strtolower($name) . '%');
        }

        if ($priceFrom !== null) {
            $qb->andWhere('p.price >= :priceFrom')
                ->setParameter('priceFrom', $priceFrom);
        }

        if ($priceTo !== null) {
            $qb->andWhere('p.price <= :priceTo')
                ->setParameter('priceTo', $priceTo);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $sortField = self::SORTABLE[$sort] ?? self::SORTABLE['id'];
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        /** @var list<Product> $items */
        $items = $qb
            ->orderBy($sortField, $sortDirection)
            ->addOrderBy('p.id', 'ASC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findById(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->em->getRepository(Product::class)
            ->findOneBy(['externalCode' => $externalCode]);
    }

    public function save(Product $product, bool $flush = true): void
    {
        $this->em->persist($product);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(Product $product, bool $flush = true): void
    {
        $this->em->remove($product);

        if ($flush) {
            $this->em->flush();
        }
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Product>
     */
    public function findLatest(int $limit = 5): array
    {
        /** @var list<Product> $products */
        $products = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $products;
    }
}
