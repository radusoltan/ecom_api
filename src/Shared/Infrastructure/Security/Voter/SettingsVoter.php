<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter for Settings resource permissions.
 *
 * Settings include:
 * - General settings (store name, logo, contact info)
 * - Email settings (SMTP, templates)
 * - Payment gateway configuration
 * - Tax settings
 * - Shipping settings
 * - API keys and integrations
 *
 * Permission Matrix:
 * - ROLE_SUPER_ADMIN: All permissions
 * - ROLE_ADMIN: View only (no edit)
 * - ROLE_MANAGER: No access
 * - ROLE_VIEWER: View only
 * - ROLE_TENANT_ADMIN: Edit tenant-specific settings
 * - ROLE_CUSTOMER: No access
 * - ROLE_VENDOR: No access
 */
final class SettingsVoter extends AbstractResourceVoter
{
    public const VIEW = 'settings.view';
    public const EDIT = 'settings.edit';

    protected function getResourceName(): string
    {
        return 'settings';
    }

    protected function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::EDIT,
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

        // ADMIN, VIEWER: can view settings
        if ($this->hasAnyRole($token, ['ROLE_ADMIN', 'ROLE_VIEWER'])) {
            return self::VIEW === $attribute;
        }

        // TENANT_ADMIN: can view and edit tenant-specific settings
        if ($this->hasRole($token, 'ROLE_TENANT_ADMIN')) {
            return in_array($attribute, [self::VIEW, self::EDIT], true);
        }

        // MANAGER, CUSTOMER, VENDOR: no access
        return false;
    }
}
