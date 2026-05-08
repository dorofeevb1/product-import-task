<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Infrastructure\Queue\RabbitQueue;

final class ImportDispatcher implements ImportDispatcherInterface
{
    public function __construct(private readonly RabbitQueue $queue)
    {
    }

    public function dispatch(ImportProductsMessage $message): void
    {
        $this->queue->publish([
            'taskId' => $message->taskId,
            'filePath' => $message->filePath,
        ]);
    }
}
