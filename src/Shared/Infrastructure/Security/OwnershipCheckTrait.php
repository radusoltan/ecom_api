<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Provides ownership verification for resource voters.
 *
 * Mitigates OWASP API3:2023 — Broken Object-Level Authorization (BOLA).
 * Use in voters to ensure non-admin users can only access their own resources.
 */
trait OwnershipCheckTrait
{
    /**
     * Check if the authenticated user owns a resource by matching email/identifier.
     *
     * Used when the resource stores the owner's email (e.g., Order::customerEmail).
     */
    protected function isResourceOwnerByEmail(TokenInterface $token, ?string $ownerEmail): bool
    {
        if (null === $ownerEmail) {
            return false;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        return $user->getUserIdentifier() === $ownerEmail;
    }

    /**
     * Check if the authenticated user owns a resource by matching user ID.
     *
     * Used when the resource stores the owner's UUID (e.g., User::id).
     */
    protected function isResourceOwnerById(TokenInterface $token, ?string $ownerId): bool
    {
        if (null === $ownerId) {
            return false;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        // UserEntity::getId() is available when the security user is our UserEntity
        if (method_exists($user, 'getId')) {
            return $user->getId() === $ownerId;
        }

        return false;
    }
}
