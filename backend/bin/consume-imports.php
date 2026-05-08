<?php

declare(strict_types=1);

use App\Domain\Import\ImportService;
use App\Domain\Import\ImportTask;
use App\Infrastructure\Doctrine\EntityManagerFactory;
use App\Infrastructure\Queue\RabbitQueue;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

$queue = new RabbitQueue(
    $_ENV['MESSENGER_TRANSPORT_DSN'] ?? 'amqp://guest:guest@rabbitmq:5672/%2f',
    $_ENV['IMPORT_QUEUE_NAME'] ?? 'import_products'
);

$queue->consume(static function (array $payload): void {
    $taskId = (string) ($payload['taskId'] ?? '');
    $filePath = (string) ($payload['filePath'] ?? '');
    if ($taskId === '' || $filePath === '') {
        throw new RuntimeException('Invalid queue payload');
    }

    $entityManager = EntityManagerFactory::create();
    $service = new ImportService($entityManager);
    $service->run($taskId, $filePath);
}, static function (array $payload, Throwable $exception): void {
    $taskId = (string) ($payload['taskId'] ?? '');
    if ($taskId === '') {
        return;
    }
    $entityManager = EntityManagerFactory::create();
    $task = $entityManager->find(ImportTask::class, $taskId);
    if ($task === null) {
        return;
    }
    $task->failed([$exception->getMessage()]);
    $entityManager->flush();
});
