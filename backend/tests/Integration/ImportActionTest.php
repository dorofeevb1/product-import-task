<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Actions\ImportAction;
use App\Domain\Import\ImportDispatcherInterface;
use App\Domain\Import\ImportProductsMessage;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;

final class ImportActionTest extends IntegrationTestCase
{
    public function testRejectsUnsupportedMimeType(): void
    {
        $action = new ImportAction($this->entityManager, $this->fakeDispatcher());
        $request = $this->requestWithUpload('products.xlsx', 'text/plain', 100);

        $this->expectException(\Slim\Exception\HttpBadRequestException::class);
        $action($request, new Response());
    }

    public function testRateLimitStopsAfterFiveAttempts(): void
    {
        $_ENV['MAX_UPLOAD_SIZE_MB'] = '10';
        $action = new ImportAction($this->entityManager, $this->fakeDispatcher());
        for ($i = 0; $i < 5; $i++) {
            $request = $this->requestWithUpload("products-{$i}.xlsx", 'application/octet-stream', 100);
            $response = $action($request, new Response());
            self::assertSame(202, $response->getStatusCode());
        }

        $this->expectException(\Slim\Exception\HttpTooManyRequestsException::class);
        $action($this->requestWithUpload('products-limit.xlsx', 'application/octet-stream', 100), new Response());
    }

    private function fakeDispatcher(): ImportDispatcherInterface
    {
        return new class implements ImportDispatcherInterface {
            public function dispatch(ImportProductsMessage $message): void
            {
            }
        };
    }

    private function requestWithUpload(string $filename, string $mimeType, int $size): \Psr\Http\Message\ServerRequestInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'upload_');
        if ($path === false) {
            throw new \RuntimeException('Cannot create temp upload path');
        }
        file_put_contents($path, str_repeat('a', $size));
        $uploaded = new UploadedFile($path, $filename, $mimeType, $size, UPLOAD_ERR_OK);

        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/import', ['REMOTE_ADDR' => '127.0.0.1'])
            ->withUploadedFiles(['file' => $uploaded]);
    }
}
