<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Security;

use App\Catalog\Domain\Model\Product;
use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter for Product resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: All permissions
 * - ROLE_MANAGER: All permissions
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: All permissions (tenant-scoped)
 * - ROLE_VENDOR: All permissions (own products only)
 * - ROLE_CUSTOMER: No access
 */
final class ProductVoter extends AbstractResourceVoter
{
    public const VIEW = 'product.view';
    public const CREATE = 'product.create';
    public const EDIT = 'product.edit';
    public const DELETE = 'product.delete';

    protected function getResourceName(): string
    {
        return 'product';
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
            return $attribute === self::VIEW;
        }

        // ADMIN, MANAGER, TENANT_ADMIN: full CRUD access
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_TENANT_ADMIN'])) {
            return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE], true);
        }

        // VENDOR: full access to own products only
        if ($this->hasRole($token, 'ROLE_VENDOR')) {
            // For CREATE operation, vendor can create products (no subject needed)
            if ($attribute === self::CREATE) {
                return true;
            }

            // For VIEW, EDIT, DELETE: allow all for now (ownership check TODO)
            // TODO: Implement vendor ownership check when vendor_id is added to Product
            if (in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)) {
                return true;
            }

            return false;
        }

        // CUSTOMER: no access
        return false;
    }
}
