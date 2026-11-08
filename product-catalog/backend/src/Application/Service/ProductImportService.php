<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use App\Domain\Entity\ProductAttribute;
use App\Domain\Entity\ProductImage;
use App\Infrastructure\Import\RowValidationException;
use App\Infrastructure\Import\XlsxDocument;
use App\Infrastructure\Import\XlsxParser;
use App\Infrastructure\Persistence\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ProductImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly ImageDownloaderInterface $imageDownloader,
        private readonly DiscountCalculator $discountCalculator,
        private readonly XlsxParser $parser,
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
        $document = $this->parser->parse($filePath);
        $total = $document->count();
        $processed = 0;
        $success = 0;
        $errors = [];

        $columns = [
            'externalCode' => $document->columnIndex('Внешний код', 'Код'),
            'name' => $document->columnIndex('Наименование'),
            'description' => $document->columnIndex('Описание'),
            'price' => $document->columnIndex('Цена: Цена продажи', 'Цена'),
            'purchasePrice' => $document->columnIndex('Закупочная цена'),
        ];
        $attributeColumns = $document->attributeColumns();
        $imageColumn = $document->imageColumn();

        foreach ($document->rows() as $row) {
            ++$processed;
            $externalCode = null;

            try {
                $data = $this->mapRow($document, $row, $columns, $attributeColumns, $imageColumn);
                $externalCode = $data['externalCode'];

                $images = $this->downloadImages(
                    $data['imageUrls'],
                    $externalCode,
                    $row['number'],
                    $errors,
                );

                $this->persistProduct($data, $images);
                ++$success;
            } catch (RowValidationException $e) {
                $errors[] = [
                    'row' => $row['number'],
                    'message' => $e->getMessage(),
                    'externalCode' => $externalCode,
                ];
            } catch (\Throwable $e) {
                $this->logger->error('Product import row failed', [
                    'row' => $row['number'],
                    'error' => $e->getMessage(),
                ]);
                $errors[] = [
                    'row' => $row['number'],
                    'message' => 'Внутренняя ошибка импорта строки: ' . $e->getMessage(),
                    'externalCode' => $externalCode,
                ];
            }

            if ($onProgress !== null && ($processed % 5 === 0 || $processed === $total)) {
                $onProgress($processed, $total, $success, $errors);
            }
        }

        return new ImportReport($total, $success, $errors);
    }

    /**
     * @param array{number: int, cells: array<int, mixed>} $row
     * @param array<string, int|null> $columns
     * @param array<string, int> $attributeColumns
     * @return array{
     *     externalCode: string,
     *     name: string,
     *     description: ?string,
     *     price: string,
     *     purchasePrice: ?string,
     *     discount: ?string,
     *     attributes: list<array{key: string, value: string}>,
     *     imageUrls: list<string>
     * }
     */
    private function mapRow(
        XlsxDocument $document,
        array $row,
        array $columns,
        array $attributeColumns,
        ?int $imageColumn,
    ): array {
        $externalCode = $this->stringValue($document->value($row, $columns['externalCode']));

        if ($externalCode === null || $externalCode === '') {
            throw new RowValidationException('Не заполнен обязательный столбец "Внешний код".');
        }

        if (mb_strlen($externalCode) > 191) {
            throw new RowValidationException('Внешний код длиннее 191 символа.');
        }

        $name = $this->stringValue($document->value($row, $columns['name']));

        if ($name === null || $name === '') {
            throw new RowValidationException('Не заполнен обязательный столбец "Наименование".');
        }

        if (mb_strlen($name) > 500) {
            throw new RowValidationException('Наименование длиннее 500 символов.');
        }

        $price = $this->discountCalculator->normalizePrice($document->value($row, $columns['price']));

        if ($price === null || (float) $price <= 0.0) {
            throw new RowValidationException('Некорректная цена продажи.');
        }

        $purchasePrice = $this->discountCalculator->normalizePrice(
            $document->value($row, $columns['purchasePrice']),
        );

        $attributes = [];
        foreach ($attributeColumns as $label => $index) {
            $value = $this->stringValue($document->value($row, $index));

            if ($value === null || $value === '') {
                continue;
            }

            $attributes[] = [
                'key' => mb_substr($label, 0, 191),
                'value' => $value,
            ];
        }

        return [
            'externalCode' => $externalCode,
            'name' => $name,
            'description' => $this->stringValue($document->value($row, $columns['description'])),
            'price' => $price,
            'purchasePrice' => $purchasePrice,
            'discount' => $this->discountCalculator->calculate($price, $purchasePrice),
            'attributes' => $attributes,
            'imageUrls' => $this->extractImageUrls($document->value($row, $imageColumn)),
        ];
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
     * @param array{
     *     externalCode: string,
     *     name: string,
     *     description: ?string,
     *     price: string,
     *     purchasePrice: ?string,
     *     discount: ?string,
     *     attributes: list<array{key: string, value: string}>
     * } $data
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

    /**
     * @return list<string>
     */
    private function extractImageUrls(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', trim((string) $value)) ?: [];
        $urls = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_URL) !== false) {
                $urls[] = $part;
            }
        }

        return array_values(array_unique($urls));
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
