<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\ValueObject\ConsentHistoryId;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Consent History Entity.
 *
 * Immutable audit trail record of consent changes.
 * Required for GDPR compliance to prove consent was given and when it was withdrawn.
 *
 * Business Rules (GDPR Article 7):
 * - Every consent change must be recorded with timestamp
 * - IP address and user agent should be captured for verification
 * - Records cannot be modified or deleted (audit trail integrity)
 * - Must be able to demonstrate consent was obtained for each processing activity
 */
final readonly class ConsentHistory
{
    private function __construct(
        private ConsentHistoryId $id,
        private CustomerId $customerId,
        private TenantId $tenantId,
        private ConsentType $consentType,
        private bool $granted,
        private ?string $ipAddress,
        private ?string $userAgent,
        private \DateTimeImmutable $createdAt
    ) {
    }

    /**
     * Record a consent change event.
     */
    public static function record(
        CustomerId $customerId,
        TenantId $tenantId,
        ConsentType $consentType,
        bool $granted,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return new self(
            id: ConsentHistoryId::generate(),
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: $consentType,
            granted: $granted,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: new \DateTimeImmutable()
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstituteFromPersistence(
        ConsentHistoryId $id,
        CustomerId $customerId,
        TenantId $tenantId,
        ConsentType $consentType,
        bool $granted,
        ?string $ipAddress,
        ?string $userAgent,
        \DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: $id,
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: $consentType,
            granted: $granted,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: $createdAt
        );
    }

    // Getters
    public function id(): ConsentHistoryId
    {
        return $this->id;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function consentType(): ConsentType
    {
        return $this->consentType;
    }

    public function granted(): bool
    {
        return $this->granted;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
