<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Security;

use App\Order\Domain\Model\Order;
use App\Shared\Infrastructure\Security\OwnershipCheckTrait;
use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter for Order resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: All permissions
 * - ROLE_MANAGER: All permissions
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: All permissions (tenant-scoped)
 * - ROLE_CUSTOMER: View own orders, create orders
 * - ROLE_VENDOR: View related orders
 */
final class OrderVoter extends AbstractResourceVoter
{
    use OwnershipCheckTrait;
    public const VIEW = 'order.view';
    public const CREATE = 'order.create';
    public const EDIT = 'order.edit';
    public const CANCEL = 'order.cancel';
    public const REFUND = 'order.refund';

    protected function getResourceName(): string
    {
        return 'order';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::CANCEL,
            self::REFUND,
        ];
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
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

        // ADMIN, MANAGER, TENANT_ADMIN: full access
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_TENANT_ADMIN'])) {
            return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::CANCEL, self::REFUND], true);
        }

        // CUSTOMER: can create orders and view/cancel own orders only
        if ($this->hasRole($token, 'ROLE_CUSTOMER')) {
            if (self::CREATE === $attribute) {
                return true;
            }

            // View and cancel own orders only (BOLA protection)
            if ($subject instanceof Order && in_array($attribute, [self::VIEW, self::CANCEL], true)) {
                return $this->isResourceOwnerByEmail($token, $subject->customerEmail());
            }

            return false;
        }

        // VENDOR: can view orders (filtered by application layer)
        if ($this->hasRole($token, 'ROLE_VENDOR')) {
            return self::VIEW === $attribute;
        }

        return false;
    }
}
