<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentWithdrawn::class)]
final class ConsentWithdrawnTest extends TestCase
{
    private ConsentId $consentId;
    private CustomerId $customerId;
    private TenantId $tenantId;
    private \DateTimeImmutable $occurredOn;

    protected function setUp(): void
    {
        $this->consentId = ConsentId::generate();
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->occurredOn = new \DateTimeImmutable();
    }

    // -------
    // Construction
    // -------

    #[Test]
    public function itStoresConsentId(): void
    {
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->consentId->equals($event->consentId));
    }

    #[Test]
    public function itStoresCustomerId(): void
    {
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->customerId->equals($event->customerId));
    }

    #[Test]
    public function itStoresPurpose(): void
    {
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::analytics(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue(ConsentPurpose::analytics()->equals($event->purpose));
    }

    #[Test]
    public function itStoresTenantId(): void
    {
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertTrue($this->tenantId->equals($event->tenantId));
    }

    #[Test]
    public function itStoresOccurredOn(): void
    {
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
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
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
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
        $event = new ConsentWithdrawn(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::profiling(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertInstanceOf(DomainEvent::class, $event);
    }

    #[Test]
    public function allConsentPurposesAreAccepted(): void
    {
        $purposes = [
            ConsentPurpose::marketing(),
            ConsentPurpose::analytics(),
            ConsentPurpose::profiling(),
            ConsentPurpose::necessary(),
            ConsentPurpose::legal(),
            ConsentPurpose::thirdPartySharing(),
        ];

        foreach ($purposes as $purpose) {
            $event = new ConsentWithdrawn(
                consentId: $this->consentId,
                customerId: $this->customerId,
                purpose: $purpose,
                tenantId: $this->tenantId,
                occurredOn: $this->occurredOn,
            );

            self::assertTrue($purpose->equals($event->purpose));
        }
    }
}
