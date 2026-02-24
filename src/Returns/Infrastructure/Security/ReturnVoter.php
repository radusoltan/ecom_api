<?php

declare(strict_types=1);

namespace App\Returns\Infrastructure\Security;

use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter for Return resource permissions.
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: All permissions
 * - ROLE_MANAGER: All permissions
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: All permissions (tenant-scoped)
 * - ROLE_CUSTOMER: View own returns only
 */
final class ReturnVoter extends AbstractResourceVoter
{
    public const VIEW = 'return.view';
    public const APPROVE = 'return.approve';
    public const REJECT = 'return.reject';
    public const PROCESS = 'return.process';

    protected function getResourceName(): string
    {
        return 'return';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::APPROVE,
            self::REJECT,
            self::PROCESS,
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

        // ADMIN, MANAGER, TENANT_ADMIN: full access
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_TENANT_ADMIN'])) {
            return true;
        }

        // CUSTOMER: can only view own returns
        if ($this->hasRole($token, 'ROLE_CUSTOMER')) {
            return self::VIEW === $attribute;
        }

        return false;
    }
}
