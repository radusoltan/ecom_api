<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;

interface ConsentRepositoryInterface
{
    public function save(Consent $consent): void;

    public function findById(ConsentId $id): ?Consent;

    /**
     * Find active consent for a specific customer and purpose.
     */
    public function findActiveByCustomerAndPurpose(
        CustomerId $customerId,
        ConsentPurpose $purpose
    ): ?Consent;

    /**
     * Find all consents for a customer (both active and withdrawn).
     *
     * @return Consent[]
     */
    public function findByCustomerId(CustomerId $customerId): array;

    /**
     * Find all active consents for a customer.
     *
     * @return Consent[]
     */
    public function findActiveByCustomerId(CustomerId $customerId): array;

    /**
     * Find all consents for a tenant.
     *
     * @return Consent[]
     */
    public function findByTenantId(TenantId $tenantId): array;

    /**
     * Check if customer has granted consent for a specific purpose.
     */
    public function hasActiveConsent(CustomerId $customerId, ConsentPurpose $purpose): bool;
}
