<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\AddAddress\AddAddressCommand;
use App\Customer\Application\Command\AddAddress\AddAddressCommandHandler;
use App\Customer\Domain\Event\CustomerAddressAdded;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddAddressCommandHandler::class)]
final class AddAddressCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private AddAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new AddAddressCommandHandler($this->customerRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path: address added, ID returned
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsAddressAndReturnsId(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (CustomerId $id) => $id->toString() === $customerId->toString()),
                self::callback(fn (TenantId $tid) => $tid->equals($tenantId))
            )
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $addressId = ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '123 Main St',
            street2: 'Apt 4B',
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping',
        ));

        self::assertIsString($addressId);
        self::assertNotEmpty($addressId);
    }

    // -----------------------------------------------------------------------
    // Address fields are stored correctly
    // -----------------------------------------------------------------------

    #[Test]
    public function itStoresAddressFieldsCorrectly(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '42 Rue de Rivoli',
            street2: null,
            city: 'Paris',
            state: null,
            postalCode: '75001',
            country: 'FR',
            type: 'billing',
        ));

        $addresses = $customer->getAddresses();
        self::assertCount(1, $addresses);
        self::assertSame('42 Rue de Rivoli', $addresses[0]->street);
        self::assertSame('Paris', $addresses[0]->city);
        self::assertSame('75001', $addresses[0]->postalCode);
        self::assertSame('FR', $addresses[0]->country);
        self::assertSame(AddressType::BILLING, $addresses[0]->type);
    }

    // -----------------------------------------------------------------------
    // Default shipping flag propagates
    // -----------------------------------------------------------------------

    #[Test]
    public function itMarksAddressAsDefaultShipping(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '10 Downing St',
            street2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 2AA',
            country: 'GB',
            type: 'shipping',
            isDefaultShipping: true,
        ));

        $defaultShipping = $customer->getDefaultShippingAddress();
        self::assertNotNull($defaultShipping);
        self::assertTrue($defaultShipping->isDefaultShipping);
    }

    // -----------------------------------------------------------------------
    // Default billing flag propagates
    // -----------------------------------------------------------------------

    #[Test]
    public function itMarksAddressAsDefaultBilling(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '1 Apple Park Way',
            street2: null,
            city: 'Cupertino',
            state: 'CA',
            postalCode: '95014',
            country: 'US',
            type: 'billing',
            isDefaultBilling: true,
        ));

        $defaultBilling = $customer->getDefaultBillingAddress();
        self::assertNotNull($defaultBilling);
        self::assertTrue($defaultBilling->isDefaultBilling);
    }

    // -----------------------------------------------------------------------
    // Domain event recorded
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsCustomerAddressAddedEvent(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'Berlin',
            state: null,
            postalCode: '10115',
            country: 'DE',
            type: 'shipping',
        ));

        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerAddressAdded::class, $events[0]);
    }

    // -----------------------------------------------------------------------
    // Exception: customer not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->customerRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Customer with ID "%s" not found', $customerId->toString()));

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping',
        ));
    }

    // -----------------------------------------------------------------------
    // Exception: invalid address (empty street)
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenStreetIsEmpty(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address street is required');

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: 'shipping',
        ));
    }

    // -----------------------------------------------------------------------
    // Exception: max addresses exceeded
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenMaxAddressLimitReached(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        for ($i = 0; $i < 10; ++$i) {
            $customer->addAddress(CustomerAddress::create(
                id: CustomerAddressId::generate(),
                street: "Street {$i}",
                street2: null,
                city: 'City',
                state: 'ST',
                postalCode: '12345',
                country: 'US',
                type: AddressType::SHIPPING,
            ));
        }

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Customer cannot have more than 10 addresses');

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '123 Overflow St',
            street2: null,
            city: 'City',
            state: 'ST',
            postalCode: '12345',
            country: 'US',
            type: 'shipping',
        ));
    }

    // -----------------------------------------------------------------------
    // Save receives the correct customer
    // -----------------------------------------------------------------------

    #[Test]
    public function itSavesCustomerWithNewAddress(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);

        $savedCustomer = null;
        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Customer $c) use (&$savedCustomer): void {
                $savedCustomer = $c;
            });

        ($this->handler)(new AddAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            street: '1 Infinite Loop',
            street2: null,
            city: 'Cupertino',
            state: 'CA',
            postalCode: '95014',
            country: 'US',
            type: 'shipping',
        ));

        self::assertNotNull($savedCustomer);
        self::assertTrue($savedCustomer->id()->equals($customerId));
        self::assertCount(1, $savedCustomer->getAddresses());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildCustomer(CustomerId $customerId, TenantId $tenantId): Customer
    {
        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'John',
            'Doe',
        );
        $customer->popEvents();

        return $customer;
    }
}
