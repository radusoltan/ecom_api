<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Security;

use App\Order\Domain\Model\Order;
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

        // CUSTOMER: can create orders and view own orders
        if ($this->hasRole($token, 'ROLE_CUSTOMER')) {
            // Can always create orders
            if (self::CREATE === $attribute) {
                return true;
            }

            // Can view and cancel own orders
            if ($subject instanceof Order && in_array($attribute, [self::VIEW, self::CANCEL], true)) {
                $user = $this->getUser($token);

                // TODO: Implement customer ownership check when customer_id is added
                // For now, allow view and cancel for all customers
                return true;
            }

            return false;
        }

        // VENDOR: can view orders related to their products
        if ($this->hasRole($token, 'ROLE_VENDOR')) {
            if (self::VIEW === $attribute) {
                // TODO: Implement vendor-related order check
                return true;
            }

            return false;
        }

        return false;
    }
}
