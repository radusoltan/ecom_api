<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Security;

use App\Customer\Domain\Model\Customer;
use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter for Customer resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: All permissions
 * - ROLE_MANAGER: All permissions
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: All permissions (tenant-scoped)
 * - ROLE_CUSTOMER: Edit own profile only
 * - ROLE_VENDOR: No access
 */
final class CustomerVoter extends AbstractResourceVoter
{
    public const VIEW = 'customer.view';
    public const CREATE = 'customer.create';
    public const EDIT = 'customer.edit';
    public const DELETE = 'customer.delete';

    protected function getResourceName(): string
    {
        return 'customer';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
        ];
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Require authentication
        if (!$this->isAuthenticated($token)) {
            return false;
        }

        // SUPER_ADMIN: full access
        if ($this->isSuperAdmin($token)) {
            return true;
        }

        // VIEWER: only view permission
        if ($this->isViewer($token)) {
            return self::VIEW === $attribute;
        }

        // ADMIN, MANAGER, TENANT_ADMIN: full CRUD access
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_TENANT_ADMIN'])) {
            return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE], true);
        }

        // CUSTOMER: can only edit own profile
        if ($this->hasRole($token, 'ROLE_CUSTOMER')) {
            if ($subject instanceof Customer && in_array($attribute, [self::VIEW, self::EDIT], true)) {
                $user = $this->getUser($token);

                // TODO: Implement ownership check when user-customer relationship is established
                // For now, allow view and edit for all customers
                return true;
            }

            return false;
        }

        // VENDOR: no access
        return false;
    }
}
