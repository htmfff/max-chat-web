<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Domain\Exception\ValidationException;
use Psr\Http\Message\UploadedFileInterface;

final class UploadedFileValidator
{
    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    public function __construct(private readonly int $maxBytes)
    {
    }

    public function validate(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Файл не был загружен (код ошибки: ' . $file->getError() . ').');
        }

        $filename = (string) $file->getClientFilename();

        if (mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new ValidationException('Допустим только файл с расширением .xlsx.');
        }

        $size = $file->getSize();
        if ($size !== null && $size > $this->maxBytes) {
            throw new ValidationException(sprintf(
                'Размер файла превышает допустимые %d МБ.',
                (int) round($this->maxBytes / 1024 / 1024),
            ));
        }

        $stream = $file->getStream();
        $tempPath = $stream->getMetadata('uri');

        if (!is_string($tempPath) || !is_file($tempPath)) {
            throw new ValidationException('Не удалось получить доступ к загруженному файлу.');
        }

        $actualSize = filesize($tempPath);
        if ($actualSize !== false && $actualSize > $this->maxBytes) {
            throw new ValidationException('Размер файла превышает допустимый лимит.');
        }

        $magic = file_get_contents($tempPath, false, null, 0, 4);
        if ($magic !== "PK\x03\x04") {
            throw new ValidationException('Файл не является корректным .xlsx (ZIP) документом.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath);

        if (!is_string($mime) || !in_array(mb_strtolower($mime), self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException('Недопустимый MIME-тип файла: ' . (string) $mime);
        }
    }
}
