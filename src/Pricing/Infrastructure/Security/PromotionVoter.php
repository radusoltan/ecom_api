<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Security;

use App\Pricing\Domain\Model\Promotion;
use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter for Promotion resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: All permissions
 * - ROLE_MANAGER: All permissions
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: All permissions (tenant-scoped)
 * - ROLE_CUSTOMER: No access
 * - ROLE_VENDOR: No access
 */
final class PromotionVoter extends AbstractResourceVoter
{
    public const VIEW = 'promotion.view';
    public const CREATE = 'promotion.create';
    public const EDIT = 'promotion.edit';
    public const DELETE = 'promotion.delete';
    public const VALIDATE_COUPON = 'promotion.validate_coupon';

    protected function getResourceName(): string
    {
        return 'promotion';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::VALIDATE_COUPON,
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

        // VIEWER: only view and validate coupon
        if ($this->isViewer($token)) {
            return in_array($attribute, [self::VIEW, self::VALIDATE_COUPON], true);
        }

        // ADMIN, MANAGER, TENANT_ADMIN: full CRUD access
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_TENANT_ADMIN'])) {
            return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::VALIDATE_COUPON], true);
        }

        // CUSTOMER, VENDOR: no access (promotions are managed by admins only)
        return false;
    }
}
