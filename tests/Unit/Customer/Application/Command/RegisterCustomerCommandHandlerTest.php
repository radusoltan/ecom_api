<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RegisterCustomerCommand;
use App\Customer\Application\Command\RegisterCustomerCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RegisterCustomerCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private RegisterCustomerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new RegisterCustomerCommandHandler($this->customerRepository);
    }

    public function testHandleRegistersNewCustomer(): void
    {
        $command = new RegisterCustomerCommand(
            customerId: CustomerId::generate()->toString(),
            tenantId: TenantId::generate()->toString(),
            email: 'john.doe@example.com',
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+12345678901'
        );

        // Email should not exist
        $this->customerRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->with('john.doe@example.com', self::isInstanceOf(TenantId::class))
            ->willReturn(null);

        // Customer should be saved
        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenEmailAlreadyExists(): void
    {
        $tenantId = TenantId::generate();
        $existingCustomer = Customer::register(
            CustomerId::generate(),
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'Existing',
            'Customer'
        );

        $command = new RegisterCustomerCommand(
            customerId: CustomerId::generate()->toString(),
            tenantId: $tenantId->toString(),
            email: 'john.doe@example.com',
            firstName: 'John',
            lastName: 'Doe'
        );

        // Email already exists
        $this->customerRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->with('john.doe@example.com', self::isInstanceOf(TenantId::class))
            ->willReturn($existingCustomer);

        // Save should not be called
        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer with email "john.doe@example.com" already exists for this tenant');

        $this->handler->__invoke($command);
    }
}
