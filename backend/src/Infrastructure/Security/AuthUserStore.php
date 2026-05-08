<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Auth\Entity\AuthUser;
use Doctrine\ORM\EntityManagerInterface;

final class AuthUserStore
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $user = $this->entityManager->getRepository(AuthUser::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$user instanceof AuthUser) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'passwordHash' => $user->getPasswordHash(),
        ];
    }

    public function createUser(string $name, string $email, string $password): void
    {
        $user = new AuthUser(
            trim($name),
            mb_strtolower(trim($email)),
            password_hash($password, PASSWORD_BCRYPT)
        );
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
