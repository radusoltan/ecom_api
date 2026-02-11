<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Customer Preferences Updated Event.
 *
 * Emitted when a customer updates their preferences (language, currency, notifications).
 */
final readonly class CustomerPreferencesUpdated
{
    /**
     * @param array<string, mixed> $oldPreferences
     * @param array<string, mixed> $newPreferences
     */
    public function __construct(
        private CustomerId $customerId,
        private TenantId $tenantId,
        private array $oldPreferences,
        private array $newPreferences,
        private \DateTimeImmutable $occurredAt
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

    /**
     * @return array<string, mixed>
     */
    public function oldPreferences(): array
    {
        return $this->oldPreferences;
    }

    /**
     * @return array<string, mixed>
     */
    public function newPreferences(): array
    {
        return $this->newPreferences;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
