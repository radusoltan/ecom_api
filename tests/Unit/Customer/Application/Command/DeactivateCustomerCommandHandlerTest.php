<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\DeactivateCustomerCommand;
use App\Customer\Application\Command\DeactivateCustomerCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeactivateCustomerCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private DeactivateCustomerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new DeactivateCustomerCommandHandler($this->customerRepository);
    }

    public function testHandleDeactivatesActiveCustomer(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe'
        );

        $command = new DeactivateCustomerCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::isInstanceOf(CustomerId::class), self::isInstanceOf(TenantId::class))
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Customer $savedCustomer) {
                return !$savedCustomer->isActive();
            }));

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenCustomerNotFound(): void
    {
        $command = new DeactivateCustomerCommand(
            customerId: CustomerId::generate()->toString(),
            tenantId: TenantId::generate()->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Customer with ID ".*" not found/');

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenAlreadyInactive(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe'
        );

        // Deactivate first
        $customer->deactivate();

        $command = new DeactivateCustomerCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer "John Doe" is already inactive');

        $this->handler->__invoke($command);
    }
}
