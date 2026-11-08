<?php

declare(strict_types=1);

use App\Application\Service\ProductImportService;
use App\Application\Service\ProductRowMapper;

$container = require dirname(__DIR__) . '/config/bootstrap.php';

/**
 * @param list<array{row: int, product: array<string, mixed>}> $rows
 */
function printProductTable(array $rows): void
{
    $headers = ['№', 'Внешний код', 'Наименование', 'Цена', 'Закупка', 'Скидка', 'Атрибуты', 'Фото'];
    $widths = [4, 26, 44, 10, 10, 8, 9, 5];

    $renderRow = static function (array $cells) use ($widths): string {
        $parts = [];

        foreach ($cells as $index => $cell) {
            $cell = (string) $cell;
            $width = $widths[$index];

            if (mb_strlen($cell) > $width) {
                $cell = mb_substr($cell, 0, $width - 1) . '…';
            }

            $parts[] = mb_str_pad($cell, $width);
        }

        return implode('  ', $parts);
    };

    $separator = str_repeat('-', array_sum($widths) + (count($widths) - 1) * 2);

    fwrite(STDOUT, $renderRow($headers) . PHP_EOL);
    fwrite(STDOUT, $separator . PHP_EOL);

    foreach ($rows as $entry) {
        $product = $entry['product'];

        fwrite(STDOUT, $renderRow([
            $entry['row'],
            $product['externalCode'],
            $product['name'],
            $product['price'],
            $product['purchasePrice'] ?? '—',
            $product['discount'] !== null ? $product['discount'] . '%' : '—',
            count($product['attributes']),
            count($product['imageUrls']),
        ]) . PHP_EOL);
    }
}

/**
 * @param list<array{row: int, product: array<string, mixed>}> $rows
 */
function writeProductCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'wb');

    if ($handle === false) {
        fwrite(STDERR, 'Не удалось открыть файл для записи: ' . $path . PHP_EOL);
        exit(1);
    }

    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, [
        'external_code',
        'name',
        'description',
        'price',
        'purchase_price',
        'discount',
        'attributes',
        'image_urls',
    ], ';');

    foreach ($rows as $entry) {
        $product = $entry['product'];
        $attributes = array_map(
            static fn (array $attribute): string => $attribute['key'] . '=' . $attribute['value'],
            $product['attributes'],
        );

        fputcsv($handle, [
            $product['externalCode'],
            $product['name'],
            $product['description'] ?? '',
            $product['price'],
            $product['purchasePrice'] ?? '',
            $product['discount'] ?? '',
            implode('; ', $attributes),
            implode(' ', $product['imageUrls']),
        ], ';');
    }

    fclose($handle);
}

/**
 * @param list<array{row: int, message: string, externalCode: ?string}> $errors
 */
function printErrors(array $errors): void
{
    fwrite(STDERR, PHP_EOL . 'Ошибки:' . PHP_EOL);

    foreach ($errors as $error) {
        $code = $error['externalCode'] !== null ? ' [' . $error['externalCode'] . ']' : '';
        fwrite(STDERR, sprintf('  строка %d%s: %s%s', $error['row'], $code, $error['message'], PHP_EOL));
    }
}

$file = null;
$save = false;
$csvPath = null;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'HELP'
            Usage: php bin/import.php [file.xlsx] [--save] [--csv=path]

              file.xlsx   таблица из задания (по умолчанию samples/import_example.xlsx)
              --save      записать строки в БД (по умолчанию только вывод таблицы)
              --csv=path  дополнительно сохранить таблицу проекта в CSV

            HELP);
        exit(0);
    }

    if ($argument === '--save') {
        $save = true;
        continue;
    }

    if (str_starts_with($argument, '--csv=')) {
        $csvPath = substr($argument, 6);
        continue;
    }

    if (str_starts_with($argument, '-')) {
        fwrite(STDERR, 'Неизвестный параметр: ' . $argument . PHP_EOL);
        exit(1);
    }

    if ($file !== null) {
        fwrite(STDERR, 'Указано больше одного файла.' . PHP_EOL);
        exit(1);
    }

    $file = $argument;
}

$file ??= dirname(__DIR__, 2) . '/samples/import_example.xlsx';

if (!is_file($file)) {
    fwrite(STDERR, 'Файл не найден: ' . $file . PHP_EOL);
    exit(1);
}

/** @var ProductRowMapper $mapper */
$mapper = $container->get(ProductRowMapper::class);

$preview = $mapper->mapFile($file);

fwrite(STDOUT, 'Таблица из задания: ' . $file . PHP_EOL);
fwrite(STDOUT, sprintf(
    'Строк: %d, корректных: %d, с ошибками: %d' . PHP_EOL . PHP_EOL,
    $preview['total'],
    count($preview['rows']),
    count($preview['errors']),
));

printProductTable($preview['rows']);

if ($csvPath !== null) {
    writeProductCsv($csvPath, $preview['rows']);
    fwrite(STDOUT, PHP_EOL . 'Таблица проекта сохранена в CSV: ' . $csvPath . PHP_EOL);
}

if ($preview['errors'] !== []) {
    printErrors($preview['errors']);
}

if ($save) {
    /** @var ProductImportService $service */
    $service = $container->get(ProductImportService::class);

    $report = $service->import($file, static function (int $processed, int $total): void {
        if ($processed % 10 === 0 || $processed === $total) {
            fwrite(STDOUT, sprintf('Импорт в БД: %d/%d' . PHP_EOL, $processed, $total));
        }
    });

    fwrite(STDOUT, sprintf(
        'Записано в БД: %d из %d, ошибок: %d' . PHP_EOL,
        $report->success,
        $report->total,
        count($report->errors),
    ));
}

exit($preview['errors'] === [] ? 0 : 2);
