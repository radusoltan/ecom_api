<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Service;

use App\Shared\Application\Service\UserLocaleProviderInterface;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Provides the authenticated user's preferred locale from the security token.
 */
final readonly class SecurityUserLocaleProvider implements UserLocaleProviderInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getPreferredLocale(): ?string
    {
        try {
            $token = $this->tokenStorage->getToken();
            if (null === $token) {
                return null;
            }

            $user = $token->getUser();
            if ($user instanceof UserEntity) {
                return $user->getPreferredLocale();
            }
        } catch (\Throwable) {
            // Security not available (e.g., CLI context)
        }

        return null;
    }
}
