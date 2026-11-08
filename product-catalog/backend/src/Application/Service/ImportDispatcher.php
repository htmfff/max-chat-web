<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Message\ImportMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportDispatcher
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function dispatch(ImportMessage $message): void
    {
        $this->bus->dispatch($message);
    }
}
