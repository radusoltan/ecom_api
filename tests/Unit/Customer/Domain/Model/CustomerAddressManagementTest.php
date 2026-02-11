<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Event\CustomerAddressAdded;
use App\Customer\Domain\Event\CustomerAddressRemoved;
use App\Customer\Domain\Event\CustomerAddressUpdated;
use App\Customer\Domain\Event\DefaultAddressChanged;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CustomerAddressManagementTest extends TestCase
{
    private Customer $customer;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->customer = Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        // Clear registration event
        $this->customer->pullDomainEvents();
    }

    public function testAddAddress(): void
    {
        $address = $this->createAddress();

        $this->customer->addAddress($address);

        $addresses = $this->customer->getAddresses();
        $this->assertCount(1, $addresses);
        $this->assertTrue($addresses[0]->id->equals($address->id));
    }

    public function testAddAddressRecordsEvent(): void
    {
        $address = $this->createAddress();

        $this->customer->addAddress($address);

        $events = $this->customer->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CustomerAddressAdded::class, $events[0]);
    }

    public function testAddAddressExceedsMaximumThrowsException(): void
    {
        // Add 10 addresses (max)
        for ($i = 0; $i < 10; ++$i) {
            $this->customer->addAddress($this->createAddress());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Customer cannot have more than 10 addresses');

        // Try to add 11th address
        $this->customer->addAddress($this->createAddress());
    }

    public function testAddMultipleAddresses(): void
    {
        $address1 = $this->createAddress();
        $address2 = $this->createAddress();
        $address3 = $this->createAddress();

        $this->customer->addAddress($address1);
        $this->customer->addAddress($address2);
        $this->customer->addAddress($address3);

        $addresses = $this->customer->getAddresses();
        $this->assertCount(3, $addresses);
    }

    public function testAddDuplicateAddressThrowsException(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);

        $this->customer->addAddress($address);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Address with ID "%s" already exists', $addressId->toString()));

        // Try to add same address again
        $this->customer->addAddress($address);
    }

    public function testUpdateAddress(): void
    {
        $addressId = CustomerAddressId::generate();
        $originalAddress = $this->createAddress($addressId, '123 Main St');
        $this->customer->addAddress($originalAddress);

        $updatedAddress = $this->createAddress($addressId, '456 Oak Ave');
        $this->customer->updateAddress($addressId, $updatedAddress);

        $addresses = $this->customer->getAddresses();
        $this->assertCount(1, $addresses);
        $this->assertSame('456 Oak Ave', $addresses[0]->street);
    }

    public function testUpdateAddressRecordsEvent(): void
    {
        $addressId = CustomerAddressId::generate();
        $originalAddress = $this->createAddress($addressId);
        $this->customer->addAddress($originalAddress);
        $this->customer->pullDomainEvents(); // Clear add event

        $updatedAddress = $this->createAddress($addressId, '456 Oak Ave');
        $this->customer->updateAddress($addressId, $updatedAddress);

        $events = $this->customer->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CustomerAddressUpdated::class, $events[0]);
    }

    public function testUpdateNonExistentAddressThrowsException(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Address with ID "%s" not found', $addressId->toString()));

        $this->customer->updateAddress($addressId, $address);
    }

    public function testUpdateAddressWithDifferentIdThrowsException(): void
    {
        $addressId1 = CustomerAddressId::generate();
        $addressId2 = CustomerAddressId::generate();

        $originalAddress = $this->createAddress($addressId1);
        $this->customer->addAddress($originalAddress);

        $updatedAddress = $this->createAddress($addressId2); // Different ID

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot change address ID during update');

        $this->customer->updateAddress($addressId1, $updatedAddress);
    }

    public function testRemoveAddress(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);
        $this->customer->addAddress($address);

        $this->customer->removeAddress($addressId);

        $addresses = $this->customer->getAddresses();
        $this->assertCount(0, $addresses);
    }

    public function testRemoveAddressRecordsEvent(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);
        $this->customer->addAddress($address);
        $this->customer->pullDomainEvents(); // Clear add event

        $this->customer->removeAddress($addressId);

        $events = $this->customer->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CustomerAddressRemoved::class, $events[0]);
    }

    public function testRemoveNonExistentAddressThrowsException(): void
    {
        $addressId = CustomerAddressId::generate();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Address with ID "%s" not found', $addressId->toString()));

        $this->customer->removeAddress($addressId);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);
        $this->customer->addAddress($address);

        $this->customer->setDefaultShippingAddress($addressId);

        $defaultShipping = $this->customer->getDefaultShippingAddress();
        $this->assertNotNull($defaultShipping);
        $this->assertTrue($defaultShipping->id->equals($addressId));
        $this->assertTrue($defaultShipping->isDefaultShipping);
    }

    public function testSetDefaultShippingRecordsEvent(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);
        $this->customer->addAddress($address);
        $this->customer->pullDomainEvents(); // Clear add event

        $this->customer->setDefaultShippingAddress($addressId);

        $events = $this->customer->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DefaultAddressChanged::class, $events[0]);
    }

    public function testSetDefaultBillingAddress(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);
        $this->customer->addAddress($address);

        $this->customer->setDefaultBillingAddress($addressId);

        $defaultBilling = $this->customer->getDefaultBillingAddress();
        $this->assertNotNull($defaultBilling);
        $this->assertTrue($defaultBilling->id->equals($addressId));
        $this->assertTrue($defaultBilling->isDefaultBilling);
    }

    public function testSetDefaultBillingRecordsEvent(): void
    {
        $addressId = CustomerAddressId::generate();
        $address = $this->createAddress($addressId);
        $this->customer->addAddress($address);
        $this->customer->pullDomainEvents(); // Clear add event

        $this->customer->setDefaultBillingAddress($addressId);

        $events = $this->customer->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DefaultAddressChanged::class, $events[0]);
    }

    public function testOnlyOneDefaultShippingAllowed(): void
    {
        $address1Id = CustomerAddressId::generate();
        $address2Id = CustomerAddressId::generate();

        $address1 = $this->createAddress($address1Id);
        $address2 = $this->createAddress($address2Id);

        $this->customer->addAddress($address1);
        $this->customer->addAddress($address2);

        $this->customer->setDefaultShippingAddress($address1Id);
        $this->customer->setDefaultShippingAddress($address2Id); // Change default

        $addresses = $this->customer->getAddresses();
        $defaultCount = 0;
        foreach ($addresses as $addr) {
            if ($addr->isDefaultShipping) {
                ++$defaultCount;
            }
        }

        $this->assertSame(1, $defaultCount);
        $this->assertTrue($this->customer->getDefaultShippingAddress()->id->equals($address2Id));
    }

    public function testOnlyOneDefaultBillingAllowed(): void
    {
        $address1Id = CustomerAddressId::generate();
        $address2Id = CustomerAddressId::generate();

        $address1 = $this->createAddress($address1Id);
        $address2 = $this->createAddress($address2Id);

        $this->customer->addAddress($address1);
        $this->customer->addAddress($address2);

        $this->customer->setDefaultBillingAddress($address1Id);
        $this->customer->setDefaultBillingAddress($address2Id); // Change default

        $addresses = $this->customer->getAddresses();
        $defaultCount = 0;
        foreach ($addresses as $addr) {
            if ($addr->isDefaultBilling) {
                ++$defaultCount;
            }
        }

        $this->assertSame(1, $defaultCount);
        $this->assertTrue($this->customer->getDefaultBillingAddress()->id->equals($address2Id));
    }

    public function testAddAddressWithDefaultShippingUnsetsOthers(): void
    {
        $address1 = CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING,
            isDefaultShipping: true
        );

        $address2 = CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '456 Oak Ave',
            street2: null,
            city: 'Los Angeles',
            state: 'CA',
            postalCode: '90001',
            country: 'US',
            type: AddressType::SHIPPING,
            isDefaultShipping: true // This should unset address1's default
        );

        $this->customer->addAddress($address1);
        $this->customer->addAddress($address2);

        $defaultShipping = $this->customer->getDefaultShippingAddress();
        $this->assertTrue($defaultShipping->id->equals($address2->id));

        // Verify only one default
        $addresses = $this->customer->getAddresses();
        $defaultCount = 0;
        foreach ($addresses as $addr) {
            if ($addr->isDefaultShipping) {
                ++$defaultCount;
            }
        }
        $this->assertSame(1, $defaultCount);
    }

    public function testGetAddresses(): void
    {
        $this->assertCount(0, $this->customer->getAddresses());

        $address1 = $this->createAddress();
        $address2 = $this->createAddress();

        $this->customer->addAddress($address1);
        $this->customer->addAddress($address2);

        $addresses = $this->customer->getAddresses();
        $this->assertCount(2, $addresses);
        $this->assertIsArray($addresses);
    }

    public function testGetDefaultShippingAddressReturnsNull(): void
    {
        $this->assertNull($this->customer->getDefaultShippingAddress());
    }

    public function testGetDefaultBillingAddressReturnsNull(): void
    {
        $this->assertNull($this->customer->getDefaultBillingAddress());
    }

    public function testGetDefaultAddressWhenNoneSet(): void
    {
        $address = $this->createAddress();
        $this->customer->addAddress($address);

        $this->assertNull($this->customer->getDefaultShippingAddress());
        $this->assertNull($this->customer->getDefaultBillingAddress());
    }

    private function createAddress(
        ?CustomerAddressId $id = null,
        string $street = '123 Main St'
    ): CustomerAddress {
        return CustomerAddress::create(
            id: $id ?? CustomerAddressId::generate(),
            street: $street,
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }
}
