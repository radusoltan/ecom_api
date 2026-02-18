<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateAllConsents;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Update All Consents Command.
 *
 * Updates all GDPR consents at once (e.g., from consent banner submission).
 * Captures IP address and user agent for audit trail compliance.
 */
final readonly class UpdateAllConsentsCommand
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public bool $marketingEmail,
        public bool $marketingSms,
        public bool $thirdPartySharing,
        public bool $analyticsTracking,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }
}
