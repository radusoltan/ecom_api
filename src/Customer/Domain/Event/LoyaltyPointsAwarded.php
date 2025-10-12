<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;


final readonly class LoyaltyPointsAwarded
{
    public function __construct(
        private CustomerId $customerId,
        private int $pointsAwarded,
        private int $newTotalPoints,
        private string $reason
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function pointsAwarded(): int
    {
        return $this->pointsAwarded;
    }

    public function newTotalPoints(): int
    {
        return $this->newTotalPoints;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
