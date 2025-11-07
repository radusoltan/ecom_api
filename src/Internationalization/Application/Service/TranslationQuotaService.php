<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Service;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;

/**
 * Service for managing translation quotas across tenants.
 *
 * Business Rules:
 * - Each tenant has a translation quota (default: 10,000 translations/month)
 * - Usage is tracked per translation operation
 * - Quota exceeded throws exception
 * - Quota can be reset monthly (typically via scheduled task)
 */
final readonly class TranslationQuotaService
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {
    }

    /**
     * Check if a tenant has quota available for a given number of translations.
     */
    public function checkQuota(TenantId $tenantId, int $count = 1): bool
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        return $tenant->hasTranslationQuotaAvailable($count);
    }

    /**
     * Increment usage for a tenant
     * Throws TranslationQuotaExceededException if quota would be exceeded.
     */
    public function incrementUsage(TenantId $tenantId, int $count = 1): void
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        $tenant->incrementTranslationUsage($count);
        $this->tenantRepository->save($tenant);
    }

    /**
     * Get current usage for a tenant.
     */
    public function getUsage(TenantId $tenantId): int
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        return $tenant->translationUsage();
    }

    /**
     * Get remaining quota for a tenant.
     */
    public function getRemainingQuota(TenantId $tenantId): int
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        return $tenant->remainingTranslationQuota();
    }

    /**
     * Get quota limit for a tenant.
     */
    public function getQuota(TenantId $tenantId): int
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        return $tenant->translationQuota();
    }

    /**
     * Reset usage for a tenant (typically called monthly).
     */
    public function resetUsage(TenantId $tenantId): void
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        $tenant->resetTranslationUsage();
        $this->tenantRepository->save($tenant);
    }

    /**
     * Set quota for a tenant.
     */
    public function setQuota(TenantId $tenantId, int $quota): void
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (null === $tenant) {
            throw new \DomainException("Tenant not found: {$tenantId->toString()}");
        }

        $tenant->setTranslationQuota($quota);
        $this->tenantRepository->save($tenant);
    }
}
