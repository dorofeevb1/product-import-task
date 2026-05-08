<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auth_refresh_tokens')]
#[ORM\Index(name: 'idx_auth_refresh_tokens_user', columns: ['subject'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 80, name: 'token_hash')]
    private string $tokenHash;

    #[ORM\Column(type: 'string', length: 180)]
    private string $subject;

    #[ORM\Column(type: 'integer', name: 'expires_at')]
    private int $expiresAt;

    #[ORM\Column(type: 'integer', name: 'created_at')]
    private int $createdAt;

    public function __construct(string $tokenHash, string $subject, int $expiresAt)
    {
        $this->tokenHash = $tokenHash;
        $this->subject = $subject;
        $this->expiresAt = $expiresAt;
        $this->createdAt = time();
    }

    public function isExpired(int $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
}
