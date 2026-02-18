<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;

final readonly class LoyaltyTierUpdated
{
    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function __construct(
        private LoyaltyProgramId $programId,
        private LoyaltyTierId $tierId,
        private array $changes,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function programId(): LoyaltyProgramId
    {
        return $this->programId;
    }

    public function tierId(): LoyaltyTierId
    {
        return $this->tierId;
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
