<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Service\DiscountCalculator;
use App\Application\Service\ProductImportService;
use App\Application\Service\ProductRowMapper;
use App\Domain\Entity\Product;
use App\Infrastructure\Import\XlsxParser;
use App\Infrastructure\Persistence\Repository\ProductRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\NullLogger;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeImageDownloader;

final class ProductImportServiceTest extends DatabaseTestCase
{
    public function testImportsSampleFileAndUpsertsOnReimport(): void
    {
        $downloader = new FakeImageDownloader();
        $service = new ProductImportService(
            $this->em,
            new ProductRepository($this->em),
            $downloader,
            new ProductRowMapper(new XlsxParser(), new DiscountCalculator()),
            new NullLogger(),
        );

        $path = dirname(__DIR__, 2) . '/../samples/import_example.xlsx';

        $progress = [];
        $report = $service->import($path, static function (int $processed, int $total) use (&$progress): void {
            $progress[] = [$processed, $total];
        });

        self::assertSame(40, $report->total);
        self::assertSame(40, $report->success);
        self::assertSame([], $report->errors);
        self::assertNotEmpty($progress);
        self::assertSame(40, $progress[array_key_last($progress)][0]);

        $products = $this->em->getRepository(Product::class)->findAll();
        self::assertCount(40, $products);

        $first = $this->em->getRepository(Product::class)
            ->findOneBy(['externalCode' => '3UHfAid1jaMiwgBuNvnsf3']);

        self::assertInstanceOf(Product::class, $first);
        self::assertSame('1320.00', $first->getPrice());
        self::assertSame('33.33', $first->getDiscount());
        self::assertGreaterThanOrEqual(10, $first->getAttributes()->count());
        self::assertCount(3, $first->getImages());
        self::assertSame('46(M)', $first->getAttributes()->filter(
            static fn ($attribute): bool => $attribute->getKey() === 'Размер',
        )->first()->getValue());

        $report2 = $service->import($path);
        self::assertSame(40, $report2->success);

        $productsAfter = $this->em->getRepository(Product::class)->findAll();
        self::assertCount(40, $productsAfter);
    }

    public function testPreviewMapsTheSourceTableWithoutWritingToDatabase(): void
    {
        $mapper = new ProductRowMapper(new XlsxParser(), new DiscountCalculator());
        $preview = $mapper->mapFile(dirname(__DIR__, 2) . '/../samples/import_example.xlsx');

        self::assertSame(40, $preview['total']);
        self::assertCount(40, $preview['rows']);
        self::assertSame([], $preview['errors']);

        $first = $preview['rows'][0]['product'];
        self::assertSame('3UHfAid1jaMiwgBuNvnsf3', $first['externalCode']);
        self::assertSame('1320.00', $first['price']);
        self::assertSame('880.00', $first['purchasePrice']);
        self::assertSame('33.33', $first['discount']);
        self::assertGreaterThanOrEqual(10, count($first['attributes']));
        self::assertCount(3, $first['imageUrls']);

        self::assertCount(0, $this->em->getRepository(Product::class)->findAll());
    }

    public function testInvalidRowsDoNotStopTheImport(): void
    {
        $path = sys_get_temp_dir() . '/import_invalid_' . bin2hex(random_bytes(6)) . '.xlsx';
        $this->createSpreadsheet($path);

        $downloader = new FakeImageDownloader();
        $service = new ProductImportService(
            $this->em,
            new ProductRepository($this->em),
            $downloader,
            new ProductRowMapper(new XlsxParser(), new DiscountCalculator()),
            new NullLogger(),
        );

        try {
            $report = $service->import($path);
        } finally {
            @unlink($path);
        }

        self::assertSame(5, $report->total);
        self::assertSame(2, $report->success);
        self::assertCount(3, $report->errors);
        self::assertSame([3, 4, 5], array_column($report->errors, 'row'));
        self::assertStringContainsString('Внешний код', $report->errors[0]['message']);
        self::assertStringContainsString('Наименование', $report->errors[1]['message']);
        self::assertStringContainsString('цена', mb_strtolower($report->errors[2]['message']));

        $products = $this->em->getRepository(Product::class)->findAll();
        self::assertCount(2, $products);
        self::assertSame(['https://example.com/a.jpg'], $downloader->downloaded);

        $second = $this->em->getRepository(Product::class)
            ->findOneBy(['externalCode' => 'OK-2']);
        self::assertInstanceOf(Product::class, $second);
        self::assertSame('200.00', $second->getPrice());
        self::assertSame('25.00', $second->getDiscount());
        self::assertSame('Red', $second->getAttributes()->first()->getValue());
        self::assertCount(1, $second->getImages());
    }

    private function createSpreadsheet(string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Внешний код',
                'Наименование',
                'Описание',
                'Цена: Цена продажи',
                'Закупочная цена',
                'Доп. поле: Цвет',
                'Доп. поле: Ссылки на фото',
            ],
            ['OK-1', 'Valid product', 'First description', 100, 80, null, null],
            ['', 'No code', null, 50, null, null, null],
            ['NO-NAME', '', null, 60, null, null, null],
            ['BAD-PRICE', 'Bad price', null, 0, null, null, null],
            ['OK-2', 'Second product', null, 200, 150, 'Red', 'https://example.com/a.jpg'],
        ]);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
