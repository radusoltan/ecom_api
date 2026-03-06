<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Event;

use App\Customer\Domain\Event\CustomerAddressAdded;
use App\Customer\Domain\Event\CustomerAddressRemoved;
use App\Customer\Domain\Event\CustomerAddressUpdated;
use App\Customer\Domain\Event\CustomerConsentChanged;
use App\Customer\Domain\Event\DataExportCompleted;
use App\Customer\Domain\Event\DataExportRequested;
use App\Customer\Domain\Event\DefaultAddressChanged;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerAddressAdded::class)]
#[CoversClass(CustomerAddressRemoved::class)]
#[CoversClass(CustomerAddressUpdated::class)]
#[CoversClass(DefaultAddressChanged::class)]
#[CoversClass(CustomerConsentChanged::class)]
#[CoversClass(DataExportRequested::class)]
#[CoversClass(DataExportCompleted::class)]
final class AddressAndConsentEventsTest extends TestCase
{
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
    }

    private function makeAddress(): CustomerAddress
    {
        return CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'Paris',
            state: null,
            postalCode: '75001',
            country: 'FR',
            type: AddressType::SHIPPING,
        );
    }

    // ---------------------------------------------------------------
    // CustomerAddressAdded
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesFieldsOnCustomerAddressAdded(): void
    {
        $address = $this->makeAddress();

        $event = new CustomerAddressAdded(
            customerId: $this->customerId,
            address: $address,
        );

        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertSame($address, $event->address());
    }

    // ---------------------------------------------------------------
    // CustomerAddressRemoved
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesFieldsOnCustomerAddressRemoved(): void
    {
        $addressId = CustomerAddressId::generate();

        $event = new CustomerAddressRemoved(
            customerId: $this->customerId,
            addressId: $addressId,
        );

        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($addressId->equals($event->addressId()));
    }

    // ---------------------------------------------------------------
    // CustomerAddressUpdated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesFieldsOnCustomerAddressUpdated(): void
    {
        $addressId = CustomerAddressId::generate();
        $newAddress = $this->makeAddress();

        $event = new CustomerAddressUpdated(
            customerId: $this->customerId,
            addressId: $addressId,
            newAddress: $newAddress,
        );

        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($addressId->equals($event->addressId()));
        self::assertSame($newAddress, $event->newAddress());
    }

    // ---------------------------------------------------------------
    // DefaultAddressChanged
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesFieldsOnDefaultAddressChanged(): void
    {
        $addressId = CustomerAddressId::generate();

        $event = new DefaultAddressChanged(
            customerId: $this->customerId,
            addressId: $addressId,
            type: 'shipping',
        );

        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($addressId->equals($event->addressId()));
        self::assertSame('shipping', $event->type());
    }

    #[Test]
    public function itSupportsBillingTypeOnDefaultAddressChanged(): void
    {
        $event = new DefaultAddressChanged(
            customerId: $this->customerId,
            addressId: CustomerAddressId::generate(),
            type: 'billing',
        );

        self::assertSame('billing', $event->type());
    }

    // ---------------------------------------------------------------
    // CustomerConsentChanged
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsWhenConsentGranted(): void
    {
        $tenantId = TenantId::generate();
        $occurredAt = new \DateTimeImmutable('2025-05-01 09:00:00');

        $event = new CustomerConsentChanged(
            customerId: $this->customerId,
            tenantId: $tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
            ipAddress: '192.168.1.1',
            occurredAt: $occurredAt,
        );

        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($tenantId->equals($event->tenantId()));
        self::assertSame('marketing_email', $event->consentType()->value);
        self::assertTrue($event->granted());
        self::assertSame('192.168.1.1', $event->ipAddress());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    #[Test]
    public function itAllowsNullIpAddressOnConsentChanged(): void
    {
        $event = new CustomerConsentChanged(
            customerId: $this->customerId,
            tenantId: TenantId::generate(),
            consentType: ConsentType::MARKETING_EMAIL,
            granted: false,
            ipAddress: null,
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertFalse($event->granted());
        self::assertNull($event->ipAddress());
    }

    // ---------------------------------------------------------------
    // DataExportRequested
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnDataExportRequested(): void
    {
        $requestId = DataExportRequestId::generate();
        $tenantId = TenantId::generate();
        $requestedAt = new \DateTimeImmutable('2025-03-10 14:00:00');

        $event = new DataExportRequested(
            requestId: $requestId,
            customerId: $this->customerId,
            tenantId: $tenantId,
            requestedAt: $requestedAt,
        );

        self::assertTrue($requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($tenantId->equals($event->tenantId()));
        self::assertSame($requestedAt, $event->requestedAt());
        self::assertSame($requestedAt, $event->occurredOn());
    }

    #[Test]
    public function itImplementsDomainEventOnDataExportRequested(): void
    {
        $event = new DataExportRequested(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: TenantId::generate(),
            requestedAt: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(\App\Shared\Domain\Event\DomainEvent::class, $event);
    }

    // ---------------------------------------------------------------
    // DataExportCompleted
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnDataExportCompleted(): void
    {
        $requestId = DataExportRequestId::generate();
        $tenantId = TenantId::generate();
        $expiresAt = new \DateTimeImmutable('+7 days');
        $completedAt = new \DateTimeImmutable('2025-03-10 15:30:00');

        $event = new DataExportCompleted(
            requestId: $requestId,
            customerId: $this->customerId,
            tenantId: $tenantId,
            filePath: '/exports/customer_data_2025.json',
            expiresAt: $expiresAt,
            completedAt: $completedAt,
        );

        self::assertTrue($requestId->equals($event->requestId()));
        self::assertTrue($this->customerId->equals($event->customerId()));
        self::assertTrue($tenantId->equals($event->tenantId()));
        self::assertSame('/exports/customer_data_2025.json', $event->filePath());
        self::assertSame($expiresAt, $event->expiresAt());
        self::assertSame($completedAt, $event->completedAt());
        self::assertSame($completedAt, $event->occurredOn());
    }

    #[Test]
    public function itImplementsDomainEventOnDataExportCompleted(): void
    {
        $event = new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: TenantId::generate(),
            filePath: '/exports/data.json',
            expiresAt: new \DateTimeImmutable('+7 days'),
            completedAt: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(\App\Shared\Domain\Event\DomainEvent::class, $event);
    }
}
