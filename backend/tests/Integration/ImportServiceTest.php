<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Import\ImportService;
use App\Domain\Import\ImportTask;
use App\Domain\Product\Entity\Product;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ImportServiceTest extends IntegrationTestCase
{
    public function testImportProcessesValidRowsAndCollectsErrors(): void
    {
        $task = new ImportTask('task-1');
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $filePath = $this->createXlsx([
            ['external_code', 'name', 'description', 'price', 'purchase_price', 'Доп. поле Цвет', 'image_urls'],
            ['SKU-1', 'Product A', 'Desc A', '100', '80', 'Red', ''],
            ['', 'Broken', 'Bad row', '120', '70', 'Blue', ''],
            ['SKU-2', 'Product B', 'Desc B', '200', '150', 'Green', ''],
        ]);

        (new ImportService($this->entityManager))->run('task-1', $filePath);
        $this->entityManager->clear();

        /** @var ImportTask $updatedTask */
        $updatedTask = $this->entityManager->find(ImportTask::class, 'task-1');
        self::assertSame('completed', $updatedTask->getStatus());
        self::assertSame(2, $updatedTask->getProcessedRows());
        self::assertSame(1, $updatedTask->getFailedRows());
        self::assertCount(1, $updatedTask->getErrors());

        $products = $this->entityManager->getRepository(Product::class)->findAll();
        self::assertCount(2, $products);
        unlink($filePath);
    }

    public function testImportUpsertsByExternalCode(): void
    {
        $taskA = new ImportTask('task-2');
        $this->entityManager->persist($taskA);
        $this->entityManager->flush();
        $fileA = $this->createXlsx([
            ['external_code', 'name', 'description', 'price', 'purchase_price', 'Доп. поле Размер', 'image_urls'],
            ['SKU-UPSERT', 'Original Name', 'Original Desc', '100', '80', 'M', ''],
        ]);
        (new ImportService($this->entityManager))->run('task-2', $fileA);

        $taskB = new ImportTask('task-3');
        $this->entityManager->persist($taskB);
        $this->entityManager->flush();
        $fileB = $this->createXlsx([
            ['external_code', 'name', 'description', 'price', 'purchase_price', 'Доп. поле Размер', 'image_urls'],
            ['SKU-UPSERT', 'Updated Name', 'Updated Desc', '120', '90', 'L', ''],
        ]);
        (new ImportService($this->entityManager))->run('task-3', $fileB);
        $this->entityManager->clear();

        $products = $this->entityManager->getRepository(Product::class)->findAll();
        self::assertCount(1, $products);
        $product = $products[0];
        self::assertSame('Updated Name', $product->getName());
        self::assertSame('SKU-UPSERT', $product->getExternalCode());
        self::assertCount(1, $product->getAttributes());

        unlink($fileA);
        unlink($fileB);
    }

    public function testCompletedTaskIsIdempotentAndSkippedOnSecondRun(): void
    {
        $task = new ImportTask('task-idempotent');
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $file = $this->createXlsx([
            ['external_code', 'name', 'description', 'price', 'purchase_price', 'image_urls'],
            ['SKU-IDEMP', 'Product 1', 'Desc', '100', '80', ''],
        ]);

        $service = new ImportService($this->entityManager);
        $service->run('task-idempotent', $file);
        $this->entityManager->clear();
        $firstCount = count($this->entityManager->getRepository(Product::class)->findAll());
        self::assertSame(1, $firstCount);

        $service = new ImportService($this->entityManager);
        $service->run('task-idempotent', $file);
        $this->entityManager->clear();
        $secondCount = count($this->entityManager->getRepository(Product::class)->findAll());
        self::assertSame(1, $secondCount);

        unlink($file);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function createXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 1),
                    $value
                );
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'import_');
        if ($path === false) {
            throw new \RuntimeException('Cannot create temp file');
        }
        $xlsxPath = $path . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);
        @unlink($path);

        return $xlsxPath;
    }
}
