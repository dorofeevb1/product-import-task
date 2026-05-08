<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OpenApiAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $content = (string) file_get_contents(__DIR__ . '/../../../openapi/openapi.yaml');
        $response->getBody()->write($content);

        return $response->withHeader('Content-Type', 'application/yaml');
    }
}
