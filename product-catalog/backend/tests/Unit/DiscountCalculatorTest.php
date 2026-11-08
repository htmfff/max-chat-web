<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Service\DiscountCalculator;
use PHPUnit\Framework\TestCase;

final class DiscountCalculatorTest extends TestCase
{
    public function testCalculatesDiscountFromSellingAndPurchasePrice(): void
    {
        $calculator = new DiscountCalculator();

        self::assertSame('33.33', $calculator->calculate('1320.00', '880.00'));
        self::assertSame('50.00', $calculator->calculate('100', '50'));
        self::assertSame('0.00', $calculator->calculate('100', '100'));
    }

    public function testReturnsNullWhenPurchasePriceIsMissing(): void
    {
        $calculator = new DiscountCalculator();

        self::assertNull($calculator->calculate('100', null));
        self::assertNull($calculator->calculate('0', '10'));
    }

    public function testNormalizesRussianPriceFormats(): void
    {
        $calculator = new DiscountCalculator();

        self::assertSame('1320.00', $calculator->normalizePrice('1 320,00'));
        self::assertSame('1320.00', $calculator->normalizePrice('1320.00'));
        self::assertSame('1320.00', $calculator->normalizePrice(1320));
        self::assertNull($calculator->normalizePrice('not a number'));
    }
}
