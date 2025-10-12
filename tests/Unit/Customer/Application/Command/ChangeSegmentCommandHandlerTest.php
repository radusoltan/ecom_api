<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\ChangeSegmentCommand;
use App\Customer\Application\Command\ChangeSegmentCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChangeSegmentCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private ChangeSegmentCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new ChangeSegmentCommandHandler($this->customerRepository);
    }

    public function testHandleChangesCustomerSegment(): void
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

        $command = new ChangeSegmentCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            newSegment: 'vip'
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
                return $savedCustomer->segment()->isVip();
            }));

        $this->handler->__invoke($command);
    }

    public function testHandleThrowsExceptionWhenCustomerNotFound(): void
    {
        $command = new ChangeSegmentCommand(
            customerId: CustomerId::generate()->toString(),
            tenantId: TenantId::generate()->toString(),
            newSegment: 'vip'
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

    public function testHandleThrowsExceptionWhenAlreadyInSegment(): void
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

        $command = new ChangeSegmentCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            newSegment: 'regular' // Already regular
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer is already in segment: regular');

        $this->handler->__invoke($command);
    }
}
