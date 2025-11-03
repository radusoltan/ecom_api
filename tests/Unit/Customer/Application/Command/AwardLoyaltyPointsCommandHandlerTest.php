<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\AwardLoyaltyPointsCommand;
use App\Customer\Application\Command\AwardLoyaltyPointsCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class AwardLoyaltyPointsCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private AwardLoyaltyPointsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new AwardLoyaltyPointsCommandHandler($this->customerRepository);
    }

    public function testItAwardsLoyaltyPointsToCustomer(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'John',
            'Doe'
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Customer $savedCustomer) {
                return $savedCustomer->loyaltyPoints() === 100;
            }));

        $command = new AwardLoyaltyPointsCommand(
            $customerId->toString(),
            $tenantId->toString(),
            100,
            'Purchase reward'
        );

        ($this->handler)($command);
    }

    public function testItThrowsExceptionWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer with ID');

        $command = new AwardLoyaltyPointsCommand(
            $customerId->toString(),
            $tenantId->toString(),
            100,
            'Test'
        );

        ($this->handler)($command);
    }

    public function testItAccumulatesMultipleAwards(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'John',
            'Doe'
        );

        $customer->awardLoyaltyPoints(50, 'First award');

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Customer $savedCustomer) {
                return $savedCustomer->loyaltyPoints() === 150;
            }));

        $command = new AwardLoyaltyPointsCommand(
            $customerId->toString(),
            $tenantId->toString(),
            100,
            'Second award'
        );

        ($this->handler)($command);
    }
}
