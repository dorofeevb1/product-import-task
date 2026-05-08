<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Infrastructure\Security\AuthUserStore;
use App\Infrastructure\Security\RefreshTokenStore;
use App\Infrastructure\Security\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;

final class AuthLoginAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AuthUserStore $userStore,
        private readonly RefreshTokenStore $refreshTokenStore,
        private readonly int $refreshTtlSeconds
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new HttpUnauthorizedException($request, 'Invalid credentials');
        }

        $normalizedUsername = strtolower($username);
        $storedUser = $this->userStore->findByEmail($normalizedUsername);
        $isValidStoredUser = $storedUser !== null && password_verify($password, (string) $storedUser['passwordHash']);
        if (!$isValidStoredUser) {
            throw new HttpUnauthorizedException($request, 'Invalid credentials');
        }

        $issued = $this->tokenService->issueAccessToken($normalizedUsername);
        $refresh = $this->tokenService->issueRefreshToken($normalizedUsername, $this->refreshTtlSeconds);
        $this->refreshTokenStore->store(
            $this->tokenService->hashToken($refresh['token']),
            $normalizedUsername,
            (int) $refresh['expiresAt']
        );
        $payload = [
            'token' => $issued['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => $issued['expiresIn'],
            'refreshToken' => $refresh['token'],
            'user' => [
                'username' => $normalizedUsername,
            ],
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
