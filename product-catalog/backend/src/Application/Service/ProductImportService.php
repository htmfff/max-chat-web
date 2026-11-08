<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use App\Domain\Entity\ProductAttribute;
use App\Domain\Entity\ProductImage;
use App\Infrastructure\Persistence\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-import-type MappedProduct from ProductRowMapper
 */
final class ProductImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly ImageDownloaderInterface $imageDownloader,
        private readonly ProductRowMapper $mapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Imports products from an .xlsx file. Invalid rows are collected in the report
     * and never interrupt the whole process.
     *
     * @param callable(int, int, int, list<array{row: int, message: string, externalCode?: string|null}>): void|null $onProgress
     */
    public function import(string $filePath, ?callable $onProgress = null): ImportReport
    {
        $mapped = $this->mapper->mapFile($filePath);
        $total = $mapped['total'];
        $errors = $mapped['errors'];
        $processed = 0;
        $success = 0;

        foreach ($mapped['rows'] as $entry) {
            ++$processed;
            $data = $entry['product'];

            try {
                $images = $this->downloadImages(
                    $data['imageUrls'],
                    $data['externalCode'],
                    $entry['row'],
                    $errors,
                );

                $this->persistProduct($data, $images);
                ++$success;
            } catch (\Throwable $e) {
                $this->logger->error('Product import row failed', [
                    'row' => $entry['row'],
                    'error' => $e->getMessage(),
                ]);
                $errors[] = [
                    'row' => $entry['row'],
                    'message' => 'Внутренняя ошибка импорта строки: ' . $e->getMessage(),
                    'externalCode' => $data['externalCode'],
                ];
            }

            if ($onProgress !== null && ($processed % 5 === 0 || $processed === count($mapped['rows']))) {
                $onProgress($processed, $total, $success, $errors);
            }
        }

        return new ImportReport($total, $success, $errors);
    }

    /**
     * Maps the source spreadsheet to the project's own product table without
     * touching the database. Used by the CLI script and safe previews.
     *
     * @return array{
     *     total: int,
     *     rows: list<array{row: int, product: MappedProduct}>,
     *     errors: list<array{row: int, message: string, externalCode: ?string}>
     * }
     */
    public function preview(string $filePath): array
    {
        return $this->mapper->mapFile($filePath);
    }

    /**
     * @param list<string> $urls
     * @param list<array{row: int, message: string, externalCode?: string|null}> $errors
     * @return list<array{url: string, path: ?string}>
     */
    private function downloadImages(array $urls, string $externalCode, int $rowNumber, array &$errors): array
    {
        $images = [];

        foreach ($urls as $position => $url) {
            $path = $this->imageDownloader->download($url, $externalCode, $position);

            if ($path === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Не удалось скачать изображение: ' . $url,
                    'externalCode' => $externalCode,
                ];
            }

            $images[] = ['url' => $url, 'path' => $path];
        }

        return $images;
    }

    /**
     * @param MappedProduct $data
     * @param list<array{url: string, path: ?string}> $images
     */
    private function persistProduct(array $data, array $images): void
    {
        $this->em->wrapInTransaction(function () use ($data, $images): void {
            $product = $this->products->findByExternalCode($data['externalCode']);

            if ($product === null) {
                $product = new Product($data['externalCode'], $data['name'], $data['price']);
            }

            $product
                ->setName($data['name'])
                ->setDescription($data['description'])
                ->setPrice($data['price'])
                ->setPurchasePrice($data['purchasePrice'])
                ->setDiscount($data['discount']);

            $product->clearAttributes();
            foreach ($data['attributes'] as $attribute) {
                $product->addAttribute(new ProductAttribute($attribute['key'], $attribute['value']));
            }

            $product->clearImages();
            foreach ($images as $position => $image) {
                $product->addImage(new ProductImage($image['url'], $image['path'], $position));
            }

            $this->products->save($product, false);
        });
    }
}
