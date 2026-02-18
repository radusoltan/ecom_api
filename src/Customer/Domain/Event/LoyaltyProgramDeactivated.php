<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class LoyaltyProgramDeactivated
{
    public function __construct(
        private LoyaltyProgramId $programId,
        private TenantId $tenantId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function programId(): LoyaltyProgramId
    {
        return $this->programId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
