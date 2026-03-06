<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateAddress\UpdateAddressCommand;
use App\Customer\Application\Command\UpdateAddress\UpdateAddressCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateAddressCommandHandler::class)]
final class UpdateAddressCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private UpdateAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new UpdateAddressCommandHandler($this->repository);
    }

    private function createCustomerWithAddress(
        CustomerId $customerId,
        TenantId $tenantId,
        CustomerAddressId $addressId,
    ): Customer {
        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'John',
            'Doe'
        );

        $address = CustomerAddress::create(
            id: $addressId,
            street: '10 Old Street',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GB',
            type: AddressType::SHIPPING,
            isDefaultShipping: false,
            isDefaultBilling: false
        );
        $customer->addAddress($address);
        $customer->pullDomainEvents(); // Clear events

        return $customer;
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdatesAddressSuccessfully(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(CustomerId::class),
                self::isInstanceOf(TenantId::class)
            )
            ->willReturn($customer);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            street: '42 New Street',
            street2: 'Floor 2',
            city: 'Manchester',
            state: 'Greater Manchester',
            postalCode: 'M1 1AE',
            country: 'GB',
            type: 'billing',
            isDefaultShipping: false,
            isDefaultBilling: true
        );

        ($this->handler)($command);

        $addresses = $customer->getAddresses();
        self::assertCount(1, $addresses);
        self::assertSame('42 New Street', $addresses[0]->street);
        self::assertSame('Floor 2', $addresses[0]->street2);
        self::assertSame('Manchester', $addresses[0]->city);
        self::assertSame('M1 1AE', $addresses[0]->postalCode);
        self::assertSame('GB', $addresses[0]->country);
        self::assertTrue($addresses[0]->isDefaultBilling);
    }

    #[Test]
    public function itUpdatesAddressStreetOnly(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::once())->method('save');

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            street: '99 Updated Road',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GB',
            type: 'shipping',
            isDefaultShipping: false,
            isDefaultBilling: false
        );

        ($this->handler)($command);

        $addresses = $customer->getAddresses();
        self::assertSame('99 Updated Road', $addresses[0]->street);
        self::assertNull($addresses[0]->street2);
    }

    #[Test]
    public function itSetsDefaultShippingOnUpdate(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::once())->method('save');

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            street: '10 Old Street',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GB',
            type: 'shipping',
            isDefaultShipping: true,
            isDefaultBilling: false
        );

        ($this->handler)($command);

        self::assertNotNull($customer->getDefaultShippingAddress());
    }

    // ---------------------------------------------------------------
    // Error paths
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            street: '42 New Street',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GB',
            type: 'shipping'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Customer with ID ".*" not found/');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenAddressNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $nonExistentAddressId = CustomerAddressId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'John',
            'Doe'
        );

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::never())->method('save');

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $nonExistentAddressId->toString(),
            street: '42 New Street',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GB',
            type: 'shipping'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Address with ID ".*" not found/');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenInvalidCountryCode(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::never())->method('save');

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            street: '42 New Street',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GBR', // Invalid: must be 2 letters
            type: 'shipping'
        );

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itUsesCorrectTenantIdWhenFindingCustomer(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn ($id) => $id->toString() === $customerId->toString()),
                self::callback(fn ($tid) => $tid->toString() === $tenantId->toString())
            )
            ->willReturn($customer);

        $this->repository->method('save');

        $command = new UpdateAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            street: '42 New Street',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 1AA',
            country: 'GB',
            type: 'shipping'
        );

        ($this->handler)($command);
    }
}
