<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use App\User\Domain\Model\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter for User resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions (including manage_roles)
 * - ROLE_ADMIN: CRUD only (no manage_roles)
 * - ROLE_MANAGER: No access
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: CRUD for tenant users only
 * - ROLE_CUSTOMER: No access
 * - ROLE_VENDOR: No access
 */
final class UserVoter extends AbstractResourceVoter
{
    public const VIEW = 'user.view';
    public const CREATE = 'user.create';
    public const EDIT = 'user.edit';
    public const DELETE = 'user.delete';
    public const MANAGE_ROLES = 'user.manage_roles';

    protected function getResourceName(): string
    {
        return 'user';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::MANAGE_ROLES,
        ];
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Require authentication
        if (!$this->isAuthenticated($token)) {
            return false;
        }

        // SUPER_ADMIN: full access including manage_roles
        if ($this->isSuperAdmin($token)) {
            return true;
        }

        // VIEWER: only view permission
        if ($this->isViewer($token)) {
            return $attribute === self::VIEW;
        }

        // ADMIN: CRUD but not manage_roles
        if ($this->hasRole($token, 'ROLE_ADMIN')) {
            return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE], true);
        }

        // TENANT_ADMIN: CRUD for tenant users only
        if ($this->hasRole($token, 'ROLE_TENANT_ADMIN')) {
            // Can view all users
            if ($attribute === self::VIEW) {
                return true;
            }

            // Can CRUD tenant users (users within same tenant)
            if ($subject instanceof User && in_array($attribute, [self::CREATE, self::EDIT, self::DELETE], true)) {
                // TODO: Implement tenant scope check when tenant relationship is established
                // For now, allow all operations for tenant admins
                return true;
            }

            return false;
        }

        // MANAGER, CUSTOMER, VENDOR: no access to user management
        return false;
    }
}
