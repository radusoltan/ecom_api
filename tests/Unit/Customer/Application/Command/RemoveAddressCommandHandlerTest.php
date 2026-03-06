<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RemoveAddress\RemoveAddressCommand;
use App\Customer\Application\Command\RemoveAddress\RemoveAddressCommandHandler;
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

#[CoversClass(RemoveAddressCommandHandler::class)]
final class RemoveAddressCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $repository;
    private RemoveAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new RemoveAddressCommandHandler($this->repository);
    }

    private function createCustomer(CustomerId $customerId, TenantId $tenantId): Customer
    {
        return Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'Jane',
            'Doe'
        );
    }

    #[Test]
    public function itRemovesAddressSuccessfully(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $addressId = CustomerAddressId::generate();

        $customer = $this->createCustomer($customerId, $tenantId);
        $address = CustomerAddress::create(
            id: $addressId,
            street: '123 Main St',
            street2: null,
            city: 'Paris',
            state: null,
            postalCode: '75001',
            country: 'FR',
            type: AddressType::SHIPPING,
            isDefaultShipping: false,
            isDefaultBilling: false
        );
        $customer->addAddress($address);

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

        $command = new RemoveAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString()
        );

        ($this->handler)($command);
    }

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

        $this->repository
            ->expects(self::never())
            ->method('save');

        $command = new RemoveAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId->toString()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer with ID');

        ($this->handler)($command);
    }
}
