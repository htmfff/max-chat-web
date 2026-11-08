<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Import\XlsxParser;
use PHPUnit\Framework\TestCase;

final class XlsxParserTest extends TestCase
{
    public function testParsesSampleWorkbook(): void
    {
        $path = dirname(__DIR__, 2) . '/../samples/import_example.xlsx';
        $document = (new XlsxParser())->parse($path);

        self::assertCount(50, $document->headers());
        self::assertCount(40, $document->rows());
        self::assertSame('Наименование', $document->headers()[4]);
        self::assertSame(5, $document->columnIndex('Внешний код'));

        $attributes = $document->attributeColumns();
        self::assertArrayHasKey('Размер', $attributes);
        self::assertArrayHasKey('Цвет', $attributes);
        self::assertArrayNotHasKey('Ссылки на фото', $attributes);
        self::assertNotNull($document->imageColumn());

        $first = $document->rows()[0];
        self::assertSame(2, $first['number']);
        self::assertSame('3UHfAid1jaMiwgBuNvnsf3', $document->value($first, 5));
    }
}
