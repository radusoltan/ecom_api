<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentGranted::class)]
final class ConsentGrantedTest extends TestCase
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
        $event = new ConsentGranted(
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
        $event = new ConsentGranted(
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
        $event = new ConsentGranted(
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
        $event = new ConsentGranted(
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
        $event = new ConsentGranted(
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
        $event = new ConsentGranted(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertSame($this->occurredOn, $event->occurredOn());
    }

    #[Test]
    public function itImplementsDomainEventInterface(): void
    {
        $event = new ConsentGranted(
            consentId: $this->consentId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::profiling(),
            tenantId: $this->tenantId,
            occurredOn: $this->occurredOn,
        );

        self::assertInstanceOf(\App\Shared\Domain\Event\DomainEvent::class, $event);
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
            $event = new ConsentGranted(
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
