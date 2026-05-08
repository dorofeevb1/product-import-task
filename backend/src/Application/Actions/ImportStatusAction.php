<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Import\ImportTask;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

final class ImportStatusAction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $task = $this->entityManager->find(ImportTask::class, $args['taskId']);
        if ($task === null) {
            throw new HttpNotFoundException($request, 'Task not found');
        }

        $response->getBody()->write(json_encode([
            'id' => $task->getId(),
            'status' => $task->getStatus(),
            'processedRows' => $task->getProcessedRows(),
            'failedRows' => $task->getFailedRows(),
            'errors' => $task->getErrors(),
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
