<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Message\ImportMessage;
use App\Application\Service\ImportDispatcher;
use App\Domain\Entity\ImportJob;
use App\Domain\Exception\ValidationException;
use App\Http\Presenter\ImportJobSerializer;
use App\Infrastructure\Import\UploadedFileValidator;
use App\Infrastructure\Persistence\Repository\ImportJobRepository;
use App\Infrastructure\Support\Uuid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class ImportController
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly ImportDispatcher $dispatcher,
        private readonly UploadedFileValidator $validator,
        private readonly ImportJobSerializer $serializer,
        private readonly string $uploadDir,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $jobs = $this->jobs->findLatest(10);

        return json_response($response, [
            'data' => array_map(fn (ImportJob $job): array => $this->serializer->toArray($job), $jobs),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;

        if (!$file instanceof UploadedFileInterface) {
            throw new ValidationException('Файл не передан в поле "file".');
        }

        $this->validator->validate($file);

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) {
            throw new ValidationException('Не удалось создать директорию для загрузки файлов.');
        }

        $uuid = Uuid::v4();
        $storedPath = $this->uploadDir . '/' . $uuid . '.xlsx';
        $file->moveTo($storedPath);

        $job = new ImportJob($uuid, (string) $file->getClientFilename(), $storedPath);
        $this->jobs->save($job);

        $this->dispatcher->dispatch(new ImportMessage((int) $job->getId()));

        return json_response($response, [
            'data' => $this->serializer->toArray($job),
        ], 202, ['Location' => '/api/imports/' . $job->getId()]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $job = $this->jobs->findById((int) $args['id']);

        if ($job === null) {
            throw new \App\Domain\Exception\NotFoundException('Задача импорта не найдена.');
        }

        return json_response($response, ['data' => $this->serializer->toArray($job)]);
    }
}
