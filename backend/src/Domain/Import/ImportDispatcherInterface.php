<?php

declare(strict_types=1);

namespace App\Domain\Import;

interface ImportDispatcherInterface
{
    public function dispatch(ImportProductsMessage $message): void;
}
