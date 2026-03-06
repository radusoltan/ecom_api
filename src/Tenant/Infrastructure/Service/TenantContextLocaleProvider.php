<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Service;

use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Application\Service\TenantLocaleProviderInterface;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;

/**
 * Provides the current tenant's default locale from the tenant context.
 */
final readonly class TenantContextLocaleProvider implements TenantLocaleProviderInterface
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function getDefaultLocale(): ?string
    {
        try {
            $tenantId = $this->tenantContext->getCurrentTenantId();
            if (null === $tenantId) {
                return null;
            }

            $tenant = $this->tenantRepository->findById($tenantId);
            if (null === $tenant) {
                return null;
            }

            return $tenant->defaultLocale()->value();
        } catch (\Throwable) {
            // Tenant context not set or repository unavailable
        }

        return null;
    }
}
