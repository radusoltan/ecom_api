<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class AccountDeletionConfirmed
{
    public function __construct(
        private DeletionRequestId $requestId,
        private CustomerId $customerId,
        private TenantId $tenantId,
        private \DateTimeImmutable $scheduledFor,
    ) {
    }

    public function requestId(): DeletionRequestId
    {
        return $this->requestId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function scheduledFor(): \DateTimeImmutable
    {
        return $this->scheduledFor;
    }
}
