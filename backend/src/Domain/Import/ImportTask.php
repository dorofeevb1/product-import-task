<?php

declare(strict_types=1);

namespace App\Domain\Import;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_tasks')]
class ImportTask
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status;

    #[ORM\Column(type: 'integer', name: 'processed_rows', options: ['default' => 0])]
    private int $processedRows = 0;

    #[ORM\Column(type: 'integer', name: 'failed_rows', options: ['default' => 0])]
    private int $failedRows = 0;

    #[ORM\Column(type: 'json')]
    private array $errors = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->status = 'queued';
    }

    public function processing(): void
    {
        $this->status = 'processing';
    }

    public function canBeProcessed(): bool
    {
        return !in_array($this->status, ['completed'], true);
    }

    public function completed(int $processedRows, int $failedRows, array $errors): void
    {
        $this->status = 'completed';
        $this->processedRows = $processedRows;
        $this->failedRows = $failedRows;
        $this->errors = $errors;
    }

    public function failed(array $errors): void
    {
        $this->status = 'failed';
        $this->errors = $errors;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getFailedRows(): int
    {
        return $this->failedRows;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
