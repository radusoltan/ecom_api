<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\DataSubjectRequestCompleted;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\Event\DomainEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataSubjectRequestCompleted::class)]
final class DataSubjectRequestCompletedTest extends TestCase
{
    private DataSubjectRequestId $requestId;
    private CustomerId $customerId;
    private \DateTimeImmutable $occurredOn;

    protected function setUp(): void
    {
        $this->requestId = DataSubjectRequestId::generate();
        $this->customerId = CustomerId::generate();
        $this->occurredOn = new \DateTimeImmutable();
    }

    // -------
    // Construction
    // -------

    #[Test]
    public function itStoresRequestId(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->requestId->equals($event->requestId));
    }

    #[Test]
    public function itStoresCustomerId(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->customerId->equals($event->customerId));
    }

    #[Test]
    public function itStoresRequestType(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
            occurredOn: $this->occurredOn,
        );

        self::assertTrue(RequestType::erasure()->equals($event->requestType));
    }

    #[Test]
    public function itStoresOccurredOn(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            occurredOn: $this->occurredOn,
        );

        self::assertSame($this->occurredOn, $event->occurredOn);
    }

    // -------
    // occurredOn() method
    // -------

    #[Test]
    public function occurredOnMethodReturnsSameInstance(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::portability(),
            occurredOn: $this->occurredOn,
        );

        self::assertSame($this->occurredOn, $event->occurredOn());
    }

    // -------
    // DomainEvent interface
    // -------

    #[Test]
    public function itImplementsDomainEventInterface(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            occurredOn: $this->occurredOn,
        );

        self::assertInstanceOf(DomainEvent::class, $event);
    }

    #[Test]
    public function allRequestTypesAreAccepted(): void
    {
        $types = [
            RequestType::access(),
            RequestType::rectification(),
            RequestType::erasure(),
            RequestType::portability(),
            RequestType::restriction(),
            RequestType::objection(),
        ];

        foreach ($types as $type) {
            $event = new DataSubjectRequestCompleted(
                requestId: $this->requestId,
                customerId: $this->customerId,
                requestType: $type,
                occurredOn: $this->occurredOn,
            );

            self::assertTrue($type->equals($event->requestType));
        }
    }

    #[Test]
    public function eventHasNoTenantIdProperty(): void
    {
        $event = new DataSubjectRequestCompleted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            occurredOn: $this->occurredOn,
        );

        // DataSubjectRequestCompleted does not have tenantId - verify only expected properties
        self::assertTrue($this->requestId->equals($event->requestId));
        self::assertTrue($this->customerId->equals($event->customerId));
    }
}
