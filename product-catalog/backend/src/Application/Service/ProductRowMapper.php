<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\Import\RowValidationException;
use App\Infrastructure\Import\XlsxDocument;
use App\Infrastructure\Import\XlsxParser;

/**
 * Maps the source spreadsheet (the table from the assignment) to the
 * project's own product table. Does not touch the database or network.
 *
 * @phpstan-type MappedProduct array{
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
final class ProductRowMapper
{
    public function __construct(
        private readonly XlsxParser $parser,
        private readonly DiscountCalculator $discountCalculator,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     rows: list<array{row: int, product: MappedProduct}>,
     *     errors: list<array{row: int, message: string, externalCode: ?string}>
     * }
     */
    public function mapFile(string $filePath): array
    {
        $document = $this->parser->parse($filePath);
        $columns = $this->columnMap($document);
        $attributeColumns = $document->attributeColumns();
        $imageColumn = $document->imageColumn();
        $rows = [];
        $errors = [];

        foreach ($document->rows() as $row) {
            $externalCode = null;

            try {
                $product = $this->mapRow($document, $row, $columns, $attributeColumns, $imageColumn);
                $externalCode = $product['externalCode'];
                $rows[] = ['row' => $row['number'], 'product' => $product];
            } catch (RowValidationException $e) {
                $errors[] = [
                    'row' => $row['number'],
                    'message' => $e->getMessage(),
                    'externalCode' => $externalCode,
                ];
            }
        }

        return [
            'total' => $document->count(),
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function columnMap(XlsxDocument $document): array
    {
        return [
            'externalCode' => $document->columnIndex('Внешний код', 'Код'),
            'name' => $document->columnIndex('Наименование'),
            'description' => $document->columnIndex('Описание'),
            'price' => $document->columnIndex('Цена: Цена продажи', 'Цена'),
            'purchasePrice' => $document->columnIndex('Закупочная цена'),
        ];
    }

    /**
     * @param array{number: int, cells: array<int, mixed>} $row
     * @param array<string, int|null> $columns
     * @param array<string, int> $attributeColumns
     * @return MappedProduct
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
