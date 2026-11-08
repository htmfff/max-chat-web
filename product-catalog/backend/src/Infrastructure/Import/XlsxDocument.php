<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

final class XlsxDocument
{
    /**
     * @param list<string> $headers
     * @param list<array{number: int, cells: array<int, mixed>}> $rows
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $rows,
    ) {
    }

    /** @return list<string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return list<array{number: int, cells: array<int, mixed>}> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function columnIndex(string ...$names): ?int
    {
        foreach ($names as $name) {
            $needle = mb_strtolower(trim($name));

            foreach ($this->headers as $index => $header) {
                if (mb_strtolower(trim($header)) === $needle) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, int> attribute label => column index
     */
    public function attributeColumns(): array
    {
        $columns = [];

        foreach ($this->headers as $index => $header) {
            $normalized = mb_strtolower(trim($header));

            if (!str_starts_with($normalized, XlsxParser::attributePrefix())) {
                continue;
            }

            if ($normalized === XlsxParser::imageHeader()) {
                continue;
            }

            $label = trim(mb_substr(trim($header), mb_strlen(XlsxParser::attributePrefix())));
            if ($label === '') {
                continue;
            }

            $columns[$label] = $index;
        }

        return $columns;
    }

    public function imageColumn(): ?int
    {
        $index = $this->columnIndex(XlsxParser::imageHeader());

        if ($index !== null) {
            return $index;
        }

        foreach ($this->headers as $i => $header) {
            if (str_contains(mb_strtolower($header), 'ссылк') && str_contains(mb_strtolower($header), 'фото')) {
                return $i;
            }
        }

        return null;
    }

    public function value(array $row, ?int $index): mixed
    {
        if ($index === null) {
            return null;
        }

        return $row['cells'][$index] ?? null;
    }
}
