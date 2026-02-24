<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Security;

use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter for Inventory resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: All permissions
 * - ROLE_MANAGER: View + Manage
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: All permissions (tenant-scoped)
 */
final class InventoryVoter extends AbstractResourceVoter
{
    public const VIEW = 'inventory.view';
    public const MANAGE = 'inventory.manage';
    public const TRANSFER = 'inventory.transfer';

    protected function getResourceName(): string
    {
        return 'inventory';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::MANAGE,
            self::TRANSFER,
        ];
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
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

        // ADMIN, TENANT_ADMIN: full access (view, manage, transfer)
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_TENANT_ADMIN'])) {
            return true;
        }

        // MANAGER: view + manage (no transfer between warehouses)
        if ($this->isManager($token)) {
            return in_array($attribute, [self::VIEW, self::MANAGE], true);
        }

        return false;
    }
}
