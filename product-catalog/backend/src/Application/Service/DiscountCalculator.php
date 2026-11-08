<?php

declare(strict_types=1);

namespace App\Application\Service;

final class DiscountCalculator
{
    public function calculate(?string $price, ?string $purchasePrice): ?string
    {
        $selling = $this->toFloat($price);
        $purchase = $this->toFloat($purchasePrice);

        if ($selling === null || $purchase === null || $selling <= 0.0) {
            return null;
        }

        $discount = ($selling - $purchase) / $selling * 100.0;

        return number_format(round($discount, 2), 2, '.', '');
    }

    public function normalizePrice(mixed $value): ?string
    {
        $float = $this->toFloat($value);

        if ($float === null) {
            return null;
        }

        return number_format($float, 2, '.', '');
    }

    public function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(["\xC2\xA0", "\xA0", ' '], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
