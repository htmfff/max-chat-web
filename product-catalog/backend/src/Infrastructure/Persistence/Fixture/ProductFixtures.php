<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Fixture;

use App\Application\Service\DiscountCalculator;
use App\Domain\Entity\Product;
use App\Domain\Entity\ProductAttribute;
use App\Domain\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;

final class ProductFixtures
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DiscountCalculator $discountCalculator,
    ) {
    }

    public function load(): void
    {
        $rows = [
            [
                'externalCode' => 'SEED-001',
                'name' => 'Бермуды мужские, Grigio/Verde, OMSA, 46(M)',
                'description' => 'Мужские трикотажные домашние бермуды. Лёгкие, мягкие и дышащие.',
                'price' => '1320.00',
                'purchasePrice' => '880.00',
                'attributes' => [
                    'Размер' => '46(M)',
                    'Цвет' => 'Grigio/Verde',
                    'Бренд' => 'OMSA',
                    'Состав' => '100% Хлопок',
                ],
                'images' => [
                    'http://catalog.collant.ru/pics/SNL-504038_p.jpg',
                    'http://catalog.collant.ru/pics/SNL-504038_m.jpg',
                ],
            ],
            [
                'externalCode' => 'SEED-002',
                'name' => 'Бермуды мужские, Grigio, OMSA, 46(M)',
                'description' => 'Мужские трикотажные домашние бермуды. Универсальная длина ниже колена.',
                'price' => '960.00',
                'purchasePrice' => '640.00',
                'attributes' => [
                    'Размер' => '46(M)',
                    'Цвет' => 'Grigio',
                    'Бренд' => 'OMSA',
                ],
                'images' => [
                    'http://catalog.collant.ru/pics/SNL-504039_p.jpg',
                ],
            ],
            [
                'externalCode' => 'SEED-003',
                'name' => 'Браслет Caramello, MINIMI, 6(XXL)',
                'description' => 'Браслет с регулируемым креплением под размер руки.',
                'price' => '780.00',
                'purchasePrice' => '520.00',
                'attributes' => [
                    'Размер' => '6(XXL)',
                    'Цвет' => 'Caramello',
                    'Бренд' => 'MINIMI',
                    'Состав' => '86% Латунь, 14% Полимер',
                ],
                'images' => [
                    'http://catalog.collant.ru/pics/SNL-456097_p.jpg',
                ],
            ],
        ];

        foreach ($rows as $row) {
            $existing = $this->em->getRepository(Product::class)
                ->findOneBy(['externalCode' => $row['externalCode']]);

            if ($existing !== null) {
                continue;
            }

            $product = new Product($row['externalCode'], $row['name'], $row['price']);
            $product
                ->setDescription($row['description'])
                ->setPurchasePrice($row['purchasePrice'])
                ->setDiscount($this->discountCalculator->calculate($row['price'], $row['purchasePrice']));

            foreach ($row['attributes'] as $key => $value) {
                $product->addAttribute(new ProductAttribute($key, $value));
            }

            foreach ($row['images'] as $position => $url) {
                $product->addImage(new ProductImage($url, null, $position));
            }

            $this->em->persist($product);
        }

        $this->em->flush();
    }
}
