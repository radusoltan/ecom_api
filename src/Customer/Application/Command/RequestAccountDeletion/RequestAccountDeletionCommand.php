<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RequestAccountDeletion;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class RequestAccountDeletionCommand
{
    public function __construct(
        public CustomerId $customerId,
        public TenantId $tenantId,
        public ?string $reason = null,
    ) {
    }
}
