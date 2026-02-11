<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\AddAddress\AddAddressCommand;
use App\Customer\Application\Command\AddAddress\AddAddressCommandHandler;
use App\Customer\Domain\Event\CustomerAddressAdded;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddAddressCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private AddAddressCommandHandler $handler;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new AddAddressCommandHandler($this->customerRepository);

        $this->tenantId = TenantId::generate();
        $this->customerId = CustomerId::generate();

        $this->customer = Customer::register(
            id: $this->customerId,
            tenantId: $this->tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        // Clear registration event
        $this->customer->pullDomainEvents();
    }

    public function testHandleSuccessfullyAddsAddress(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: 'Apt 4B',
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping'
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn ($id) => $id->equals($this->customerId)),
                self::isInstanceOf(TenantId::class)
            )
            ->willReturn($this->customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $addressId = ($this->handler)($command);

        $this->assertNotEmpty($addressId);
        $this->assertIsString($addressId);

        // Verify address was added to customer
        $addresses = $this->customer->getAddresses();
        $this->assertCount(1, $addresses);
        $this->assertSame('123 Main St', $addresses[0]->street);
        $this->assertSame('Apt 4B', $addresses[0]->street2);
        $this->assertSame('New York', $addresses[0]->city);
        $this->assertSame('NY', $addresses[0]->state);
        $this->assertSame('10001', $addresses[0]->postalCode);
        $this->assertSame('US', $addresses[0]->country);
        $this->assertSame(AddressType::SHIPPING, $addresses[0]->type);
    }

    public function testHandleWithDefaultShipping(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping',
            isDefaultShipping: true
        );

        $this->customerRepository
            ->method('findById')
            ->willReturn($this->customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        ($this->handler)($command);

        $defaultShipping = $this->customer->getDefaultShippingAddress();
        $this->assertNotNull($defaultShipping);
        $this->assertTrue($defaultShipping->isDefaultShipping);
    }

    public function testHandleWithDefaultBilling(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'billing',
            isDefaultBilling: true
        );

        $this->customerRepository
            ->method('findById')
            ->willReturn($this->customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        ($this->handler)($command);

        $defaultBilling = $this->customer->getDefaultBillingAddress();
        $this->assertNotNull($defaultBilling);
        $this->assertTrue($defaultBilling->isDefaultBilling);
    }

    public function testHandleCustomerNotFoundThrowsException(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping'
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Customer with ID "%s" not found', $this->customerId->toString()));

        ($this->handler)($command);
    }

    public function testHandleInvalidAddressThrowsException(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '', // Invalid: empty street
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping'
        );

        $this->customerRepository
            ->method('findById')
            ->willReturn($this->customer);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address street is required');

        ($this->handler)($command);
    }

    public function testHandleMaxAddressesExceededThrowsException(): void
    {
        // Add 10 addresses to customer (max limit)
        for ($i = 0; $i < 10; ++$i) {
            $this->customer->addAddress(
                \App\Customer\Domain\ValueObject\CustomerAddress::create(
                    id: \App\Customer\Domain\ValueObject\CustomerAddressId::generate(),
                    street: "Street {$i}",
                    street2: null,
                    city: 'City',
                    state: 'ST',
                    postalCode: '12345',
                    country: 'US',
                    type: AddressType::SHIPPING
                )
            );
        }

        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping'
        );

        $this->customerRepository
            ->method('findById')
            ->willReturn($this->customer);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Customer cannot have more than 10 addresses');

        ($this->handler)($command);
    }

    public function testHandleDispatchesDomainEvents(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping'
        );

        $this->customerRepository
            ->method('findById')
            ->willReturn($this->customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        ($this->handler)($command);

        $events = $this->customer->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CustomerAddressAdded::class, $events[0]);
    }

    public function testHandleSavesCustomer(): void
    {
        $command = new AddAddressCommand(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping'
        );

        $this->customerRepository
            ->method('findById')
            ->willReturn($this->customer);

        $savedCustomer = null;
        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (Customer $customer) use (&$savedCustomer) {
                $savedCustomer = $customer;
            });

        ($this->handler)($command);

        $this->assertNotNull($savedCustomer);
        $this->assertTrue($savedCustomer->id()->equals($this->customerId));
        $this->assertCount(1, $savedCustomer->getAddresses());
    }
}
