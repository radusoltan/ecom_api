<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;

final readonly class LoyaltyTierRemoved
{
    public function __construct(
        private LoyaltyProgramId $programId,
        private LoyaltyTierId $tierId,
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

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
