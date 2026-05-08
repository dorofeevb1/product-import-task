<?php

declare(strict_types=1);

namespace App\Domain\Import;

final class ImportProductsMessage
{
    public function __construct(public readonly string $taskId, public readonly string $filePath)
    {
    }
}
