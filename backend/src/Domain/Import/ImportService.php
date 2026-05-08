<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Product\Entity\Product;
use App\Domain\Product\Entity\ProductAttribute;
use App\Domain\Product\Entity\ProductImage;
use App\Domain\Product\Repository\ProductRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ImportService
{
    /** @var array<string, bool> */
    private array $hostSafetyCache = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function run(string $taskId, string $filePath): void
    {
        $task = $this->entityManager->find(ImportTask::class, $taskId);
        if ($task === null) {
            return;
        }
        if (!$task->canBeProcessed()) {
            return;
        }
        $task->processing();
        $this->entityManager->flush();

        try {
            $fastModeEnabled = in_array(
                mb_strtolower((string) ($_ENV['FAST_IMPORT_MODE'] ?? '0')),
                ['1', 'true', 'yes'],
                true
            );
            if ($fastModeEnabled) {
                $this->runFastImport($task, $filePath);

                return;
            }

            $repository = new ProductRepository($this->entityManager);
            $errors = [];
            $processed = 0;
            $failed = 0;
            $http = new Client([
                'timeout' => (float) ($_ENV['IMAGE_DOWNLOAD_TIMEOUT_SECONDS'] ?? 3.0),
                'connect_timeout' => (float) ($_ENV['IMAGE_CONNECT_TIMEOUT_SECONDS'] ?? 1.5),
                'allow_redirects' => false,
            ]);

            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $maxRow = $sheet->getHighestDataRow();
            $maxColumn = $sheet->getHighestDataColumn();
            if ($maxRow < 1) {
                $task->failed(['File is empty']);
                $this->entityManager->flush();
                return;
            }

            $headerRow = $sheet->rangeToArray('A1:' . $maxColumn . '1', null, true, true, true)[1] ?? [];
            $headers = array_map(static fn ($v) => trim((string) $v), $headerRow);

            for ($rowIndex = 2; $rowIndex <= $maxRow; $rowIndex++) {
                try {
                    $row = $sheet->rangeToArray('A' . $rowIndex . ':' . $maxColumn . $rowIndex, null, true, true, true)[$rowIndex] ?? [];
                    $normalized = [];
                    foreach ($headers as $col => $header) {
                        if ($header !== '') {
                            $normalized[$header] = trim((string) ($row[$col] ?? ''));
                        }
                    }

                    $externalCode = $this->valueByAliases($normalized, [
                        'external_code',
                        'external code',
                        'код',
                        'код товара',
                        'внешний код',
                        'артикул',
                        'sku',
                    ]);
                    $name = $this->valueByAliases($normalized, [
                        'name',
                        'наименование',
                        'название',
                    ]);
                    $description = $this->valueByAliases($normalized, [
                        'description',
                        'описание',
                    ]);
                    $price = (float) $this->valueByAliases($normalized, [
                        'price',
                        'sale_price',
                        'цена',
                        'цена продажи',
                        'цена цена продажи',
                    ], '0');
                    $purchase = (float) $this->valueByAliases($normalized, [
                        'purchase_price',
                        'закупочная цена',
                        'себестоимость',
                    ], '0');

                    if ($externalCode === '' || $name === '' || $price <= 0) {
                        throw new \RuntimeException('Invalid required fields');
                    }

                    $discount = (($price - $purchase) / $price) * 100;
                    $existing = $repository->findByExternalCode($externalCode);

                    $this->entityManager->wrapInTransaction(function () use (
                        $existing,
                        $externalCode,
                        $name,
                        $description,
                        $price,
                        $discount,
                        $normalized,
                        $http
                    ): void {
                        $product = $existing ?? new Product(
                            $externalCode,
                            $name,
                            $description,
                            number_format($price, 2, '.', ''),
                            number_format($discount, 2, '.', '')
                        );
                        if ($existing !== null) {
                            $product->update($name, $description, number_format($price, 2, '.', ''), number_format($discount, 2, '.', ''));
                            $product->clearAttributes();
                            $product->clearImages();
                        }

                        foreach ($normalized as $header => $value) {
                            if ($value === '' || !$this->shouldPersistAsAttribute($header)) {
                                continue;
                            }
                            $product->addAttribute(new ProductAttribute($product, $header, $value));
                        }

                        $skipImages = in_array(
                            mb_strtolower((string) ($_ENV['IMPORT_SKIP_IMAGES'] ?? '0')),
                            ['1', 'true', 'yes'],
                            true
                        );
                        $storeSourceOnly = in_array(
                            mb_strtolower((string) ($_ENV['IMPORT_STORE_SOURCE_URL_ONLY'] ?? '0')),
                            ['1', 'true', 'yes'],
                            true
                        );
                        if (!$skipImages) {
                            $imageUrlsRaw = $this->valueByAliases($normalized, [
                                'image_urls',
                                'image_urls_csv',
                                'images',
                                'image_urls_list',
                                'ссылки на фото',
                                'доп поле ссылки на фото',
                                'ссылки на изображения',
                                'изображения',
                                'картинки',
                            ]);
                            $imageUrls = array_values(array_filter(array_map('trim', explode(',', $imageUrlsRaw))));
                            $maxImagesPerProduct = max(0, (int) ($_ENV['MAX_IMAGES_PER_PRODUCT'] ?? 2));
                            if ($maxImagesPerProduct > 0) {
                                $imageUrls = array_slice($imageUrls, 0, $maxImagesPerProduct);
                            }

                            foreach ($imageUrls as $imageUrl) {
                                try {
                                    if ($storeSourceOnly) {
                                        $product->addImage(new ProductImage($product, $imageUrl, $imageUrl));
                                        continue;
                                    }
                                    $localPath = $this->downloadImage($http, $imageUrl, $externalCode);
                                    $product->addImage(new ProductImage($product, $imageUrl, $localPath));
                                } catch (\Throwable) {
                                    // Skip broken or slow image URLs to keep import throughput predictable.
                                    continue;
                                }
                            }
                        }

                        $this->entityManager->persist($product);
                    });
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = sprintf('Row %d: %s', $rowIndex, $e->getMessage());
                }
            }

            $task->completed($processed, $failed, $errors);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $task->failed([$e->getMessage()]);
            $this->entityManager->flush();
        }
    }

    private function runFastImport(ImportTask $task, string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = $sheet->getHighestDataRow();
        $maxColumn = $sheet->getHighestDataColumn();
        if ($maxRow < 1) {
            $task->failed(['File is empty']);
            $this->entityManager->flush();

            return;
        }

        $headerRow = $sheet->rangeToArray('A1:' . $maxColumn . '1', null, true, true, true)[1] ?? [];
        $headers = array_map(static fn ($v) => trim((string) $v), $headerRow);
        $connection = $this->entityManager->getConnection();
        $batchSize = max(100, (int) ($_ENV['FAST_IMPORT_BATCH_SIZE'] ?? 5000));

        $processed = 0;
        $failed = 0;
        $errors = [];
        $rows = [];

        for ($rowIndex = 2; $rowIndex <= $maxRow; $rowIndex++) {
            try {
                $row = $sheet->rangeToArray('A' . $rowIndex . ':' . $maxColumn . $rowIndex, null, true, true, true)[$rowIndex] ?? [];
                $normalized = [];
                foreach ($headers as $col => $header) {
                    if ($header !== '') {
                        $normalized[$header] = trim((string) ($row[$col] ?? ''));
                    }
                }

                $externalCode = $this->valueByAliases($normalized, [
                    'external_code',
                    'external code',
                    'код',
                    'код товара',
                    'внешний код',
                    'артикул',
                    'sku',
                ]);
                $name = $this->valueByAliases($normalized, [
                    'name',
                    'наименование',
                    'название',
                ]);
                $description = $this->valueByAliases($normalized, [
                    'description',
                    'описание',
                ]);
                $price = (float) $this->valueByAliases($normalized, [
                    'price',
                    'sale_price',
                    'цена',
                    'цена продажи',
                    'цена цена продажи',
                ], '0');
                $purchase = (float) $this->valueByAliases($normalized, [
                    'purchase_price',
                    'закупочная цена',
                    'себестоимость',
                ], '0');

                if ($externalCode === '' || $name === '' || $price <= 0) {
                    throw new \RuntimeException('Invalid required fields');
                }

                $discount = (($price - $purchase) / $price) * 100;
                $rows[] = [
                    'external_code' => $externalCode,
                    'name' => $name,
                    'description' => $description,
                    'price' => number_format($price, 2, '.', ''),
                    'discount' => number_format($discount, 2, '.', ''),
                ];
                $processed++;

                if (count($rows) >= $batchSize) {
                    $this->bulkUpsertProducts($connection, $rows);
                    $rows = [];
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = sprintf('Row %d: %s', $rowIndex, $e->getMessage());
            }
        }

        if ($rows !== []) {
            $this->bulkUpsertProducts($connection, $rows);
        }

        $errors[] = 'FAST_IMPORT_MODE: imported core product fields only; attributes and images are not processed in this mode.';
        $task->completed($processed, $failed, $errors);
        $this->entityManager->flush();
    }

    /**
     * @param list<array{external_code:string,name:string,description:string,price:string,discount:string}> $rows
     */
    private function bulkUpsertProducts(Connection $connection, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($rows as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?)';
            $params[] = $row['external_code'];
            $params[] = $row['name'];
            $params[] = $row['description'];
            $params[] = $row['price'];
            $params[] = $row['discount'];
        }

        $sql = '
            INSERT INTO products (external_code, name, description, price, discount)
            VALUES ' . implode(', ', $placeholders) . '
            ON CONFLICT (external_code)
            DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description,
                price = EXCLUDED.price,
                discount = EXCLUDED.discount
        ';
        $connection->executeStatement($sql, $params);
    }

    private function downloadImage(Client $http, string $url, string $externalCode): string
    {
        $parts = $this->guardImageUrl($url);
        $targetDir = __DIR__ . '/../../../storage/images/' . $externalCode;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create image directory');
        }

        $parsedPath = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $baseName = basename($parsedPath);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName) ?: uniqid('img_', true) . '.jpg';
        $targetPath = $targetDir . '/' . $filename;
        $response = $http->get((string) $parts['safeUrl'], [
            'http_errors' => true,
            'headers' => [
                'Host' => (string) $parts['host'],
            ],
        ]);
        $contentType = mb_strtolower((string) ($response->getHeaderLine('Content-Type') ?: ''));
        if ($contentType === '' || !str_starts_with($contentType, 'image/')) {
            throw new \RuntimeException('Downloaded file is not an image');
        }
        $body = (string) $response->getBody();
        $maxImageBytes = (int) ($_ENV['MAX_IMAGE_SIZE_MB'] ?? 5) * 1024 * 1024;
        if (strlen($body) > $maxImageBytes) {
            throw new \RuntimeException('Image is too large');
        }
        file_put_contents($targetPath, $body);

        return str_replace(__DIR__ . '/../../../', '', $targetPath);
    }

    /**
     * @return array{host:string,safeUrl:string}
     */
    private function guardImageUrl(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('Invalid image URL');
        }
        $scheme = mb_strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Unsupported image URL scheme');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443], true)) {
            throw new \RuntimeException('Unsupported image URL port');
        }
        $host = (string) $parts['host'];
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            throw new \RuntimeException('Blocked private image host');
        }
        if (array_key_exists($host, $this->hostSafetyCache)) {
            if (!$this->hostSafetyCache[$host]) {
                throw new \RuntimeException('Blocked private image host');
            }
            return ['host' => $host, 'safeUrl' => $url];
        }

        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            $this->hostSafetyCache[$host] = false;
            throw new \RuntimeException('Host cannot be resolved');
        }
        foreach ($ips as $ip) {
            $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if (!$isPublic) {
                $this->hostSafetyCache[$host] = false;
                throw new \RuntimeException('Blocked private image host');
            }
        }
        $this->hostSafetyCache[$host] = true;

        return ['host' => $host, 'safeUrl' => $url];
    }

    /**
     * @return list<string>
     */
    private function resolveHostIps(string $host): array
    {
        $addresses = [];

        $ipv4 = gethostbynamel($host) ?: [];
        foreach ($ipv4 as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $addresses[] = $ip;
            }
        }

        if (function_exists('dns_get_record')) {
            $aaaaRecords = dns_get_record($host, DNS_AAAA);
            if (is_array($aaaaRecords)) {
                foreach ($aaaaRecords as $record) {
                    $ipv6 = (string) ($record['ipv6'] ?? '');
                    if ($ipv6 !== '' && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                        $addresses[] = $ipv6;
                    }
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    private function valueByAliases(array $normalized, array $aliases, string $default = ''): string
    {
        $indexed = [];
        foreach ($normalized as $key => $value) {
            $normalizedKey = $this->normalizeHeader((string) $key);
            if ($normalizedKey !== '') {
                $indexed[$normalizedKey] = (string) $value;
            }
        }

        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeHeader($alias);
            if ($normalizedAlias !== '' && array_key_exists($normalizedAlias, $indexed)) {
                return trim((string) $indexed[$normalizedAlias]);
            }
        }

        return $default;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(['_', '—', '-', '.', ':', '(', ')', "\t"], ' ', $header);
        $header = preg_replace('/[^\\p{L}\\p{N}\\s]+/u', ' ', $header) ?? '';
        $header = preg_replace('/\s+/u', ' ', $header) ?? '';

        return trim($header);
    }

    private function shouldPersistAsAttribute(string $header): bool
    {
        $normalized = $this->normalizeHeader($header);
        if ($normalized === '') {
            return false;
        }

        $excluded = [
            'external code',
            'внешний код',
            'код',
            'код товара',
            'sku',
            'name',
            'наименование',
            'название',
            'description',
            'описание',
            'price',
            'sale price',
            'цена',
            'цена продажи',
            'цена цена продажи',
            'purchase price',
            'закупочная цена',
            'себестоимость',
            'image urls',
            'image urls csv',
            'images',
            'image urls list',
            'ссылки на изображения',
            'изображения',
            'картинки',
            'ссылки на фото',
            'доп поле ссылки на фото',
        ];

        return !in_array($normalized, $excluded, true);
    }
}
