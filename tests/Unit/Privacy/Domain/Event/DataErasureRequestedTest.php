<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataErasureRequested::class)]
final class DataErasureRequestedTest extends TestCase
{
    private DataSubjectRequestId $requestId;
    private CustomerId $customerId;
    private TenantId $tenantId;
    private \DateTimeImmutable $occurredOn;

    protected function setUp(): void
    {
        $this->requestId = DataSubjectRequestId::generate();
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->occurredOn = new \DateTimeImmutable();
    }

    // -------
    // Construction
    // -------

    #[Test]
    public function itStoresRequestId(): void
    {
        $event = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->requestId->equals($event->requestId));
    }

    #[Test]
    public function itStoresCustomerId(): void
    {
        $event = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->customerId->equals($event->customerId));
    }

    #[Test]
    public function itStoresTenantId(): void
    {
        $event = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->tenantId->equals($event->tenantId));
    }

    #[Test]
    public function itStoresOccurredOn(): void
    {
        $event = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
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
        $event = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
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
        $event = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertInstanceOf(DomainEvent::class, $event);
    }

    #[Test]
    public function differentCustomersAndRequestsAreDistinct(): void
    {
        $requestId2 = DataSubjectRequestId::generate();
        $customerId2 = CustomerId::generate();

        $event1 = new DataErasureRequested(
            requestId: $this->requestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        $event2 = new DataErasureRequested(
            requestId: $requestId2,
            customerId: $customerId2,
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertFalse($event1->requestId->equals($event2->requestId));
        self::assertFalse($event1->customerId->equals($event2->customerId));
    }
}
