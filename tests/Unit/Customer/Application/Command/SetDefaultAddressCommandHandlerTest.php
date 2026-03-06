<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\SetDefaultAddress\SetDefaultAddressCommand;
use App\Customer\Application\Command\SetDefaultAddress\SetDefaultAddressCommandHandler;
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

#[CoversClass(SetDefaultAddressCommandHandler::class)]
final class SetDefaultAddressCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private SetDefaultAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new SetDefaultAddressCommandHandler($this->repository);
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
            'Jane',
            'Smith'
        );

        $address = CustomerAddress::create(
            id: $addressId,
            street: '5 Broadway',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::BOTH,
            isDefaultShipping: false,
            isDefaultBilling: false
        );
        $customer->addAddress($address);
        $customer->pullDomainEvents();

        return $customer;
    }

    // ---------------------------------------------------------------
    // Happy path — shipping
    // ---------------------------------------------------------------

    #[Test]
    public function itSetsDefaultShippingAddress(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        self::assertNull($customer->getDefaultShippingAddress());

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            type: 'shipping'
        );

        ($this->handler)($command);

        self::assertNotNull($customer->getDefaultShippingAddress());
        self::assertTrue($customer->getDefaultShippingAddress()->isDefaultShipping);
    }

    // ---------------------------------------------------------------
    // Happy path — billing
    // ---------------------------------------------------------------

    #[Test]
    public function itSetsDefaultBillingAddress(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        self::assertNull($customer->getDefaultBillingAddress());

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            type: 'billing'
        );

        ($this->handler)($command);

        self::assertNotNull($customer->getDefaultBillingAddress());
        self::assertTrue($customer->getDefaultBillingAddress()->isDefaultBilling);
    }

    #[Test]
    public function itHandlesTypeWithExtraWhitespace(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::once())->method('save');

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            type: '  shipping  '
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

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
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

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'Jane',
            'Smith'
        );

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::never())->method('save');

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: CustomerAddressId::generate()->toString(),
            type: 'shipping'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Address with ID ".*" not found/');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsForInvalidAddressType(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::never())->method('save');

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            type: 'both' // Invalid: only shipping or billing allowed
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid address type for default/');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsForEmptyAddressType(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomerWithAddress($customerId, $tenantId, $addressId);

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::never())->method('save');

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString(),
            type: ''
        );

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itOverridesPreviousDefaultShipping(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'Jane',
            'Smith'
        );

        $firstAddressId = CustomerAddressId::generate();
        $firstAddress = CustomerAddress::create(
            id: $firstAddressId,
            street: '1 First Street',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING,
            isDefaultShipping: true,
            isDefaultBilling: false
        );
        $customer->addAddress($firstAddress);

        $secondAddressId = CustomerAddressId::generate();
        $secondAddress = CustomerAddress::create(
            id: $secondAddressId,
            street: '2 Second Street',
            street2: null,
            city: 'Boston',
            state: 'MA',
            postalCode: '02101',
            country: 'US',
            type: AddressType::SHIPPING,
            isDefaultShipping: false,
            isDefaultBilling: false
        );
        $customer->addAddress($secondAddress);
        $customer->pullDomainEvents();

        $this->repository->method('findById')->willReturn($customer);
        $this->repository->expects(self::once())->method('save');

        $command = new SetDefaultAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $secondAddressId->toString(),
            type: 'shipping'
        );

        ($this->handler)($command);

        $defaultShipping = $customer->getDefaultShippingAddress();
        self::assertNotNull($defaultShipping);
        self::assertTrue($defaultShipping->id->equals($secondAddressId));
    }
}
