<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Auth\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;

final class RefreshTokenStore
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function store(string $tokenHash, string $subject, int $expiresAt): void
    {
        $token = new RefreshToken($tokenHash, $subject, $expiresAt);
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function consume(string $tokenHash, int $now): ?string
    {
        $token = $this->entityManager->find(RefreshToken::class, $tokenHash);
        if (!$token instanceof RefreshToken) {
            return null;
        }

        $subject = $token->isExpired($now) ? null : $token->getSubject();
        $this->entityManager->remove($token);
        $this->entityManager->flush();

        return $subject;
    }

    public function revokeByHash(string $tokenHash): void
    {
        $token = $this->entityManager->find(RefreshToken::class, $tokenHash);
        if ($token instanceof RefreshToken) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
        }
    }
}
