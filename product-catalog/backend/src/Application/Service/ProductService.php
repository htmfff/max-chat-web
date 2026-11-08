<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use App\Domain\Entity\ProductAttribute;
use App\Domain\Entity\ProductImage;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Persistence\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ProductService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly DiscountCalculator $discountCalculator,
    ) {
    }

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function list(
        int $page,
        int $limit,
        ?string $name = null,
        ?float $priceFrom = null,
        ?float $priceTo = null,
        string $sort = 'id',
        string $direction = 'asc',
    ): array {
        return $this->products->paginate($page, $limit, $name, $priceFrom, $priceTo, $sort, $direction);
    }

    public function get(int $id): Product
    {
        $product = $this->products->findById($id);

        if ($product === null) {
            throw new NotFoundException('Товар не найден.');
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): Product
    {
        $externalCode = trim((string) ($input['externalCode'] ?? ''));

        if ($externalCode === '') {
            throw new ValidationException('Поле externalCode обязательно.');
        }

        if ($this->products->findByExternalCode($externalCode) !== null) {
            throw new ValidationException('Товар с таким externalCode уже существует.');
        }

        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            throw new ValidationException('Поле name обязательно.');
        }

        $price = $this->discountCalculator->normalizePrice($input['price'] ?? null);

        if ($price === null || (float) $price <= 0.0) {
            throw new ValidationException('Поле price должно быть положительным числом.');
        }

        $purchasePrice = $this->discountCalculator->normalizePrice($input['purchasePrice'] ?? null);

        $product = new Product($externalCode, $name, $price);
        $product
            ->setDescription($this->nullableString($input['description'] ?? null))
            ->setPurchasePrice($purchasePrice)
            ->setDiscount($this->discountCalculator->calculate($price, $purchasePrice));

        $this->applyAttributes($product, $input['attributes'] ?? []);
        $this->applyImages($product, $input['images'] ?? []);

        $this->em->wrapInTransaction(function () use ($product): void {
            $this->products->save($product, false);
        });

        return $product;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input): Product
    {
        $product = $this->get($id);

        if (array_key_exists('externalCode', $input)) {
            $externalCode = trim((string) $input['externalCode']);

            if ($externalCode === '') {
                throw new ValidationException('Поле externalCode не может быть пустым.');
            }

            $existing = $this->products->findByExternalCode($externalCode);
            if ($existing !== null && $existing->getId() !== $product->getId()) {
                throw new ValidationException('Товар с таким externalCode уже существует.');
            }

            $product->setExternalCode($externalCode);
        }

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                throw new ValidationException('Поле name не может быть пустым.');
            }
            $product->setName($name);
        }

        $price = $product->getPrice();
        if (array_key_exists('price', $input)) {
            $normalized = $this->discountCalculator->normalizePrice($input['price']);
            if ($normalized === null || (float) $normalized <= 0.0) {
                throw new ValidationException('Поле price должно быть положительным числом.');
            }
            $price = $normalized;
            $product->setPrice($normalized);
        }

        if (array_key_exists('description', $input)) {
            $product->setDescription($this->nullableString($input['description']));
        }

        $purchasePrice = $product->getPurchasePrice();
        if (array_key_exists('purchasePrice', $input)) {
            $purchasePrice = $this->discountCalculator->normalizePrice($input['purchasePrice']);
            $product->setPurchasePrice($purchasePrice);
        }

        $product->setDiscount($this->discountCalculator->calculate($price, $purchasePrice));

        if (array_key_exists('attributes', $input)) {
            $this->applyAttributes($product, $input['attributes']);
        }

        if (array_key_exists('images', $input)) {
            $this->applyImages($product, $input['images']);
        }

        $this->em->wrapInTransaction(function () use ($product): void {
            $this->products->save($product, false);
        });

        return $product;
    }

    public function delete(int $id): void
    {
        $product = $this->get($id);

        $this->em->wrapInTransaction(function () use ($product): void {
            $this->products->remove($product, false);
        });
    }

    private function applyAttributes(Product $product, mixed $attributes): void
    {
        if (!is_array($attributes)) {
            throw new ValidationException('Поле attributes должно быть массивом.');
        }

        $product->clearAttributes();

        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                throw new ValidationException('Каждый атрибут должен быть объектом {key, value}.');
            }

            $key = trim((string) ($attribute['key'] ?? ''));

            if ($key === '') {
                throw new ValidationException('У атрибута должен быть непустой key.');
            }

            $product->addAttribute(new ProductAttribute(
                mb_substr($key, 0, 191),
                $this->nullableString($attribute['value'] ?? null),
            ));
        }
    }

    private function applyImages(Product $product, mixed $images): void
    {
        if (!is_array($images)) {
            throw new ValidationException('Поле images должно быть массивом.');
        }

        $product->clearImages();

        foreach (array_values($images) as $position => $image) {
            if (!is_array($image)) {
                throw new ValidationException('Каждое изображение должно быть объектом {url, path}.');
            }

            $url = trim((string) ($image['url'] ?? ''));

            if ($url === '') {
                throw new ValidationException('У изображения должен быть непустой url.');
            }

            $path = $this->nullableString($image['path'] ?? null);
            $product->addImage(new ProductImage($url, $path, $position));
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
