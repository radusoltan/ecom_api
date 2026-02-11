<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\CancelAccountDeletion;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CancelAccountDeletionCommand
{
    public function __construct(
        public DeletionRequestId $requestId,
        public CustomerId $customerId,
        public TenantId $tenantId
    ) {
    }
}
