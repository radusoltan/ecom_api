<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\Event\DomainEvent;

final readonly class ConsentGranted implements DomainEvent
{
    public function __construct(
        public ConsentId $consentId,
        public CustomerId $customerId,
        public ConsentPurpose $purpose,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
