<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\Shared\Infrastructure\Encryption\BlindIndexService;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Custom user provider that uses blind indexes for email lookups.
 *
 * Required because encrypted email columns cannot be searched directly.
 * The blind index (HMAC-SHA256) provides deterministic lookup without decryption.
 *
 * @implements UserProviderInterface<UserEntity>
 */
final readonly class BlindIndexUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BlindIndexService $blindIndexService,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $blindIndex = $this->blindIndexService->generate($identifier);

        $user = $this->entityManager->getRepository(UserEntity::class)
            ->findOneBy(['emailBlindIndex' => $blindIndex]);

        if (null === $user) {
            $exception = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof UserEntity) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s, got %s.', UserEntity::class, $user::class));
        }

        $refreshed = $this->entityManager->find(UserEntity::class, $user->getId());

        if (null === $refreshed) {
            $exception = new UserNotFoundException(sprintf('User with ID "%s" not found.', $user->getId()));
            $exception->setUserIdentifier($user->getUserIdentifier());
            throw $exception;
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return UserEntity::class === $class || is_subclass_of($class, UserEntity::class);
    }
}
