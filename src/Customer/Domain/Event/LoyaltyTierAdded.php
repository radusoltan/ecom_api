<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;

final readonly class LoyaltyTierAdded
{
    public function __construct(
        private LoyaltyProgramId $programId,
        private LoyaltyTierId $tierId,
        private string $tierName,
        private int $threshold,
        private \DateTimeImmutable $occurredAt
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

    public function tierName(): string
    {
        return $this->tierName;
    }

    public function threshold(): int
    {
        return $this->threshold;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
