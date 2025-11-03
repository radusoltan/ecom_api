<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

final readonly class DataErasureRequested implements DomainEvent
{
    public function __construct(
        public DataSubjectRequestId $requestId,
        public CustomerId $customerId,
        public DateTimeImmutable $occurredOn
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
