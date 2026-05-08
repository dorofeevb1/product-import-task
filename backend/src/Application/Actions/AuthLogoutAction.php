<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Infrastructure\Security\RefreshTokenStore;
use App\Infrastructure\Security\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthLogoutAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly RefreshTokenStore $refreshTokenStore
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $refreshToken = trim((string) ($body['refreshToken'] ?? ''));
        if ($refreshToken !== '') {
            $this->refreshTokenStore->revokeByHash($this->tokenService->hashToken($refreshToken));
        }

        $response->getBody()->write((string) json_encode(['ok' => true], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
