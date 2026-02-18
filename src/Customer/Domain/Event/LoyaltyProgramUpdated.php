<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class LoyaltyProgramUpdated
{
    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function __construct(
        private LoyaltyProgramId $programId,
        private TenantId $tenantId,
        private array $changes,
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

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
