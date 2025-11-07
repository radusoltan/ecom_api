<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\ActivateCustomerCommand;
use App\Customer\Application\Command\ActivateCustomerCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ActivateCustomerCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private ActivateCustomerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new ActivateCustomerCommandHandler($this->customerRepository);
    }

    public function testHandleActivatesInactiveCustomer(): void
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

        $command = new ActivateCustomerCommand(
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
                return $savedCustomer->isActive();
            }));

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenCustomerNotFound(): void
    {
        $command = new ActivateCustomerCommand(
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Customer with ID ".*" not found/');

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenAlreadyActive(): void
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

        // Already active
        $command = new ActivateCustomerCommand(
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer "John Doe" is already active');

        $this->handler->__invoke($command);
    }
}
