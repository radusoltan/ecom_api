<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Abstract base class for resource-based voters.
 *
 * Provides common functionality for permission checking across different resources.
 * Each concrete voter should implement:
 * - getResourceName(): returns the resource name (e.g., 'product', 'order')
 * - getSupportedAttributes(): returns array of supported permissions
 * - voteOnAttribute(): implements permission logic per role
 *
 * Permission naming convention: {resource}.{action}
 * Examples: 'product.view', 'order.create', 'customer.edit'
 *
 * @extends Voter<string, mixed>
 */
abstract class AbstractResourceVoter extends Voter
{
    /**
     * Get the resource name for this voter (e.g., 'product', 'order').
     */
    abstract protected function getResourceName(): string;

    /**
     * Get supported attributes (permissions) for this resource.
     * Example: ['product.view', 'product.create', 'product.edit', 'product.delete']
     *
     * @return list<string>
     */
    abstract protected function getSupportedAttributes(): array;

    /**
     * Check if this voter supports the given attribute and subject.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Support attribute if it's in the list of supported attributes
        return in_array($attribute, $this->getSupportedAttributes(), true);
    }

    /**
     * Check if the user has a specific role.
     */
    protected function hasRole(TokenInterface $token, string $role): bool
    {
        return in_array($role, $token->getRoleNames(), true);
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param list<string> $roles
     */
    protected function hasAnyRole(TokenInterface $token, array $roles): bool
    {
        $userRoles = $token->getRoleNames();

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given roles.
     *
     * @param list<string> $roles
     */
    protected function hasAllRoles(TokenInterface $token, array $roles): bool
    {
        $userRoles = $token->getRoleNames();

        foreach ($roles as $role) {
            if (!in_array($role, $userRoles, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the authenticated user from the token.
     */
    protected function getUser(TokenInterface $token): ?UserInterface
    {
        $user = $token->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    /**
     * Check if user is authenticated.
     */
    protected function isAuthenticated(TokenInterface $token): bool
    {
        return $this->getUser($token) !== null;
    }

    /**
     * Check if user has super admin privileges (full access to everything).
     */
    protected function isSuperAdmin(TokenInterface $token): bool
    {
        return $this->hasRole($token, 'ROLE_SUPER_ADMIN');
    }

    /**
     * Check if user has admin privileges (ROLE_ADMIN or ROLE_SUPER_ADMIN).
     */
    protected function isAdmin(TokenInterface $token): bool
    {
        return $this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
    }

    /**
     * Check if user has manager privileges.
     */
    protected function isManager(TokenInterface $token): bool
    {
        return $this->hasRole($token, 'ROLE_MANAGER');
    }

    /**
     * Check if user has viewer privileges (read-only).
     */
    protected function isViewer(TokenInterface $token): bool
    {
        return $this->hasRole($token, 'ROLE_VIEWER');
    }

    /**
     * Check if user has any admin-level role (SUPER_ADMIN, ADMIN, MANAGER, TENANT_ADMIN).
     */
    protected function hasAdminPrivileges(TokenInterface $token): bool
    {
        return $this->hasAnyRole($token, [
            'ROLE_SUPER_ADMIN',
            'ROLE_ADMIN',
            'ROLE_MANAGER',
            'ROLE_TENANT_ADMIN',
        ]);
    }

    /**
     * Default implementation: SUPER_ADMIN has full access to everything.
     * Override this method in concrete voters for specific permission logic.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Require authentication
        if (!$this->isAuthenticated($token)) {
            return false;
        }

        // SUPER_ADMIN has full access to everything
        if ($this->isSuperAdmin($token)) {
            return true;
        }

        // Concrete voters must implement specific logic
        return false;
    }
}
