<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain Event: Notification preferences have been updated.
 *
 * Fired when a customer changes their notification preferences.
 * Useful for:
 * - Audit trail (GDPR compliance)
 * - Analytics on preference changes
 * - Syncing with external notification services
 */
final readonly class NotificationPreferencesUpdated
{
    /**
     * @param array<string, bool> $oldPreferences
     * @param array<string, bool> $newPreferences
     */
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public array $oldPreferences,
        public array $newPreferences,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
