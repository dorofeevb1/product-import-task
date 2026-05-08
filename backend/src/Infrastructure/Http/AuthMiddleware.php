<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Security\TokenService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = (string) ($request->getHeaderLine('Authorization') ?: '');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return $this->unauthorized('Missing bearer token');
        }

        $token = trim(substr($authorization, 7));
        if ($token === '' || !$this->tokenService->verifyAccessToken($token)) {
            return $this->unauthorized('Invalid token');
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401);
        $response->getBody()->write((string) json_encode([
            'code' => 'HTTP_401',
            'message' => $message,
            'details' => [],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
