<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Infrastructure\Security\RefreshTokenStore;
use App\Infrastructure\Security\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;

final class AuthRefreshAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly RefreshTokenStore $refreshTokenStore,
        private readonly int $refreshTtlSeconds
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $refreshToken = trim((string) ($body['refreshToken'] ?? ''));
        if ($refreshToken === '') {
            throw new HttpUnauthorizedException($request, 'Invalid refresh token');
        }

        $payload = $this->tokenService->parseRefreshToken($refreshToken);
        if ($payload === null) {
            throw new HttpUnauthorizedException($request, 'Invalid refresh token');
        }

        $storedSubject = $this->refreshTokenStore->consume(
            $this->tokenService->hashToken($refreshToken),
            time()
        );
        if ($storedSubject === null || $storedSubject !== (string) $payload['sub']) {
            throw new HttpUnauthorizedException($request, 'Invalid refresh token');
        }

        $access = $this->tokenService->issueAccessToken($storedSubject);
        $nextRefresh = $this->tokenService->issueRefreshToken($storedSubject, $this->refreshTtlSeconds);
        $this->refreshTokenStore->store(
            $this->tokenService->hashToken($nextRefresh['token']),
            $storedSubject,
            (int) $nextRefresh['expiresAt']
        );

        $response->getBody()->write((string) json_encode([
            'token' => $access['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => $access['expiresIn'],
            'refreshToken' => $nextRefresh['token'],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
