<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Infrastructure\Security\AuthUserStore;
use App\Infrastructure\Security\RefreshTokenStore;
use App\Infrastructure\Security\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

final class AuthRegisterAction
{
    public function __construct(
        private readonly AuthUserStore $userStore,
        private readonly TokenService $tokenService,
        private readonly RefreshTokenStore $refreshTokenStore,
        private readonly int $refreshTtlSeconds
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $name = trim((string) ($body['name'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($name === '') {
            throw new HttpBadRequestException($request, 'Name is required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpBadRequestException($request, 'Valid email is required');
        }
        if (mb_strlen($password) < 6) {
            throw new HttpBadRequestException($request, 'Password must be at least 6 characters');
        }
        if ($this->userStore->findByEmail($email) !== null) {
            $response->getBody()->write((string) json_encode([
                'code' => 'HTTP_409',
                'message' => 'Email already exists',
                'details' => [],
            ], JSON_THROW_ON_ERROR));

            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->userStore->createUser($name, $email, $password);
        $issued = $this->tokenService->issueAccessToken($email);
        $refresh = $this->tokenService->issueRefreshToken($email, $this->refreshTtlSeconds);
        $this->refreshTokenStore->store(
            $this->tokenService->hashToken($refresh['token']),
            $email,
            (int) $refresh['expiresAt']
        );
        $payload = [
            'token' => $issued['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => $issued['expiresIn'],
            'refreshToken' => $refresh['token'],
            'user' => [
                'username' => $email,
                'name' => $name,
            ],
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }
}
