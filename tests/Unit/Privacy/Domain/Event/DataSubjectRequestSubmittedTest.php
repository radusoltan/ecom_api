<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataSubjectRequestSubmitted::class)]
final class DataSubjectRequestSubmittedTest extends TestCase
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
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->requestId->equals($event->requestId));
    }

    #[Test]
    public function itStoresCustomerId(): void
    {
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->customerId->equals($event->customerId));
    }

    #[Test]
    public function itStoresRequestType(): void
    {
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue(RequestType::erasure()->equals($event->requestType));
    }

    #[Test]
    public function itStoresTenantId(): void
    {
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->tenantId->equals($event->tenantId));
    }

    #[Test]
    public function itStoresOccurredOn(): void
    {
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
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
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::portability(),
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
        $event = new DataSubjectRequestSubmitted(
            requestId: $this->requestId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            tenantId: $this->tenantId,
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
            $event = new DataSubjectRequestSubmitted(
                requestId: $this->requestId,
                customerId: $this->customerId,
                requestType: $type,
                tenantId: $this->tenantId,
                occurredOn: $this->occurredOn,
            );

            self::assertTrue($type->equals($event->requestType));
        }
    }
}
