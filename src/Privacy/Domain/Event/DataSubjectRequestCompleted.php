<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Event;

use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

final readonly class DataSubjectRequestCompleted implements DomainEvent
{
    public function __construct(
        public DataSubjectRequestId $requestId,
        public RequestType $requestType,
        public DateTimeImmutable $occurredOn
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
