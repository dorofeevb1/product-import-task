<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpException;
use Throwable;

final class JsonErrorHandler
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function __invoke(Throwable $exception): ResponseInterface
    {
        $status = $exception instanceof HttpException ? $exception->getCode() : 500;
        if ($status < 400 || $status > 599) {
            $status = 500;
        }
        $code = $exception instanceof HttpException ? 'HTTP_' . $status : 'INTERNAL_ERROR';
        $payload = [
            'code' => $code,
            'message' => $status >= 500 ? 'Internal server error' : $exception->getMessage(),
            'details' => [],
        ];
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
