<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateCustomerCommand;
use App\Customer\Application\Command\UpdateCustomerCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateCustomerCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private UpdateCustomerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new UpdateCustomerCommandHandler($this->customerRepository);
    }

    public function testHandleUpdatesCustomerProfile(): void
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

        $command = new UpdateCustomerCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            firstName: 'Jane',
            lastName: 'Smith',
            phoneNumber: '+19876543210'
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
                return $savedCustomer->firstName() === 'Jane'
                    && $savedCustomer->lastName() === 'Smith'
                    && $savedCustomer->phoneNumber() === '+19876543210';
            }));

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenCustomerNotFound(): void
    {
        $command = new UpdateCustomerCommand(
            customerId: CustomerId::generate()->toString(),
            tenantId: TenantId::generate()->toString(),
            firstName: 'Jane',
            lastName: 'Smith'
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
}
