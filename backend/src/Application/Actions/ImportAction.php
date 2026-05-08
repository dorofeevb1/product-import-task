<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Import\ImportDispatcherInterface;
use App\Domain\Import\ImportProductsMessage;
use App\Domain\Import\ImportTask;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpTooManyRequestsException;

final class ImportAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportDispatcherInterface $dispatcher
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guardRateLimit($request);
        $uploaded = $request->getUploadedFiles()['file'] ?? null;
        if ($uploaded === null) {
            throw new HttpBadRequestException($request, 'File is required');
        }

        $filename = (string) $uploaded->getClientFilename();
        if (!str_ends_with(mb_strtolower($filename), '.xlsx')) {
            throw new HttpBadRequestException($request, 'Only .xlsx is allowed');
        }
        $maxUploadMb = (int) ($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 10);
        $maxUploadBytes = $maxUploadMb * 1024 * 1024;
        $size = (int) ($uploaded->getSize() ?? 0);
        if ($size <= 0 || $size > $maxUploadBytes) {
            throw new HttpBadRequestException($request, sprintf('Invalid file size. Max %dMB.', $maxUploadMb));
        }
        $mediaType = mb_strtolower((string) ($uploaded->getClientMediaType() ?? ''));
        $allowedMime = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
        ];
        if ($mediaType !== '' && !in_array($mediaType, $allowedMime, true)) {
            throw new HttpBadRequestException($request, 'Unsupported file MIME type');
        }

        $taskId = self::uuid();
        $targetDir = __DIR__ . '/../../../storage/imports';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new HttpBadRequestException($request, 'Cannot create import dir');
        }
        $targetPath = $targetDir . '/' . $taskId . '.xlsx';
        $uploaded->moveTo($targetPath);

        $task = new ImportTask($taskId);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new ImportProductsMessage($taskId, $targetPath));
        $response->getBody()->write(json_encode(['taskId' => $taskId, 'status' => 'queued'], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(202);
    }

    private function guardRateLimit(ServerRequestInterface $request): void
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $connection = $this->entityManager->getConnection();
        $now = time();
        $windowSec = 60;
        $maxAttempts = 5;
        $updated = $connection->executeStatement(
            'UPDATE import_rate_limits
             SET attempts = CASE WHEN (:now - window_start) >= :window THEN 1 ELSE attempts + 1 END,
                 window_start = CASE WHEN (:now - window_start) >= :window THEN :now ELSE window_start END
             WHERE ip = :ip
               AND ((:now - window_start) >= :window OR attempts < :max_attempts)',
            [
                'ip' => $ip,
                'now' => $now,
                'window' => $windowSec,
                'max_attempts' => $maxAttempts,
            ]
        );
        if ($updated > 0) {
            return;
        }
        try {
            $connection->insert('import_rate_limits', [
                'ip' => $ip,
                'attempts' => 1,
                'window_start' => $now,
            ]);
            return;
        } catch (\Throwable) {
            $blocked = $connection->fetchAssociative(
                'SELECT attempts, window_start FROM import_rate_limits WHERE ip = :ip',
                ['ip' => $ip]
            );
            $attempts = (int) ($blocked['attempts'] ?? 0);
            $windowStart = (int) ($blocked['window_start'] ?? $now);
            if (($now - $windowStart) < $windowSec && $attempts >= $maxAttempts) {
                throw new HttpTooManyRequestsException($request, 'Rate limit exceeded');
            }
        }
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
