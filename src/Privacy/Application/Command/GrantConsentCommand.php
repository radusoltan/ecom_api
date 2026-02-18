<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GrantConsentCommand
{
    public function __construct(
        public TenantId $tenantId,
        public CustomerId $customerId,
        public ConsentPurpose $purpose,
        public string $ipAddress,
        public string $userAgent,
        public string $consentText,
        public string $consentVersion,
    ) {
    }
}
