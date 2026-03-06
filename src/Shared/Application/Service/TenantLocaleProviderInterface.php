<?php

declare(strict_types=1);

namespace App\Shared\Application\Service;

/**
 * Provides the current tenant's default locale.
 *
 * Abstracts tenant locale lookup so Shared layer doesn't depend on Tenant context.
 */
interface TenantLocaleProviderInterface
{
    /**
     * Get the current tenant's default locale, if any.
     */
    public function getDefaultLocale(): ?string;
}
