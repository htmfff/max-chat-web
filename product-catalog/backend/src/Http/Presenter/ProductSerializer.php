<?php

declare(strict_types=1);

namespace App\Http\Presenter;

use App\Domain\Entity\Product;

final class ProductSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Product $product, bool $withRelations = true): array
    {
        $data = [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => (float) $product->getPrice(),
            'purchasePrice' => $product->getPurchasePrice() !== null ? (float) $product->getPurchasePrice() : null,
            'discount' => $product->getDiscount() !== null ? (float) $product->getDiscount() : null,
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
        ];

        if (!$withRelations) {
            return $data;
        }

        $attributes = [];
        foreach ($product->getAttributes() as $attribute) {
            $attributes[] = [
                'id' => $attribute->getId(),
                'key' => $attribute->getKey(),
                'value' => $attribute->getValue(),
            ];
        }

        $images = [];
        foreach ($product->getImages() as $image) {
            $images[] = [
                'id' => $image->getId(),
                'url' => $image->getUrl(),
                'path' => $image->getPath(),
                'position' => $image->getPosition(),
            ];
        }

        $data['attributes'] = $attributes;
        $data['images'] = $images;

        return $data;
    }
}
