<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Domain\Exception\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class XlsxParser
{
    private const ATTRIBUTE_PREFIX = 'доп. поле:';
    private const IMAGE_HEADER = 'доп. поле: ссылки на фото';

    public function parse(string $filePath): XlsxDocument
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new ValidationException('Файл импорта не найден или недоступен для чтения.');
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            throw new ValidationException('Не удалось прочитать .xlsx файл: ' . $e->getMessage(), 0, $e);
        }

        $sheet = $spreadsheet->getActiveSheet();
        /** @var list<array<int, mixed>> $data */
        $data = $sheet->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        if ($data === []) {
            throw new ValidationException('Файл не содержит данных.');
        }

        /** @var list<string> $headers */
        $headers = array_map(
            static fn (mixed $value): string => trim((string) $value),
            array_shift($data),
        );

        $rows = [];
        foreach ($data as $index => $cells) {
            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $rows[] = [
                'number' => $index + 2,
                'cells' => $cells,
            ];
        }

        return new XlsxDocument($headers, $rows);
    }

    /**
     * @param array<int, mixed> $cells
     */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    public static function attributePrefix(): string
    {
        return self::ATTRIBUTE_PREFIX;
    }

    public static function imageHeader(): string
    {
        return self::IMAGE_HEADER;
    }
}
