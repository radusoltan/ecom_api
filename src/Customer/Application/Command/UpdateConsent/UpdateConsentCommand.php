<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateConsent;

use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Update Consent Command.
 *
 * Updates a single GDPR consent for a customer.
 * Captures IP address and user agent for audit trail compliance.
 *
 * @deprecated Use Privacy context GrantConsentCommand/WithdrawConsentCommand instead.
 *             Privacy is the GDPR source of truth; PrivacyConsentSyncSubscriber
 *             propagates consent decisions to Customer context automatically.
 */
final readonly class UpdateConsentCommand
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public ConsentType $consentType,
        public bool $granted,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }
}
