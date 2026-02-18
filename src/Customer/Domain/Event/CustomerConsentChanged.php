<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Customer Consent Changed Event.
 *
 * Emitted when a customer grants or withdraws consent for a specific data processing activity.
 * Used for audit logging and triggering downstream actions (e.g., unsubscribe from mailing lists).
 */
final readonly class CustomerConsentChanged
{
    public function __construct(
        private CustomerId $customerId,
        private TenantId $tenantId,
        private ConsentType $consentType,
        private bool $granted,
        private ?string $ipAddress,
        private \DateTimeImmutable $occurredAt,
    ) {
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

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
