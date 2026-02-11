<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ReleaseDeletionHold;

use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ReleaseDeletionHoldCommand
{
    public function __construct(
        public DeletionRequestId $requestId,
        public TenantId $tenantId
    ) {
    }
}
