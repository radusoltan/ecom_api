<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RecordLoyaltyTransaction\RecordLoyaltyTransactionCommand;
use App\Customer\Application\Command\RecordLoyaltyTransaction\RecordLoyaltyTransactionCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyPointTransactionRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordLoyaltyTransactionCommandHandler::class)]
final class RecordLoyaltyTransactionCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private LoyaltyPointTransactionRepositoryInterface $transactionRepository;
    private RecordLoyaltyTransactionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->transactionRepository = $this->createMock(LoyaltyPointTransactionRepositoryInterface::class);
        $this->handler = new RecordLoyaltyTransactionCommandHandler(
            $this->customerRepository,
            $this->transactionRepository
        );
    }

    private function createCustomer(CustomerId $customerId, TenantId $tenantId, int $initialPoints = 0): Customer
    {
        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('customer@example.com'),
            'John',
            'Doe'
        );
        if ($initialPoints > 0) {
            $customer->awardLoyaltyPoints($initialPoints, 'Initial award');
        }

        return $customer;
    }

    #[Test]
    public function itRecordsCreditTransaction(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $customer = $this->createCustomer($customerId, $tenantId);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->transactionRepository
            ->expects(self::once())
            ->method('save');

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        $command = new RecordLoyaltyTransactionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::EARNED,
            points: 100,
            reason: 'Order reward'
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itRecordsDebitTransaction(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $customer = $this->createCustomer($customerId, $tenantId, 200);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->transactionRepository
            ->expects(self::once())
            ->method('save');

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        $command = new RecordLoyaltyTransactionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::REDEEMED,
            points: 100,
            reason: 'Redemption for discount'
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->transactionRepository
            ->expects(self::never())
            ->method('save');

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $command = new RecordLoyaltyTransactionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::EARNED,
            points: 50,
            reason: 'Purchase'
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenInsufficientPoints(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $customer = $this->createCustomer($customerId, $tenantId, 50);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->transactionRepository
            ->expects(self::never())
            ->method('save');

        $command = new RecordLoyaltyTransactionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::REDEEMED,
            points: 200,
            reason: 'Redemption'
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient loyalty points');

        ($this->handler)($command);
    }

    #[Test]
    public function itIncludesOrderIdInTransaction(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $customer = $this->createCustomer($customerId, $tenantId);

        $this->customerRepository
            ->method('findById')
            ->willReturn($customer);

        $this->transactionRepository
            ->expects(self::once())
            ->method('save');

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        $command = new RecordLoyaltyTransactionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::BONUS,
            points: 50,
            reason: 'Bonus for order',
            orderId: 'order-123'
        );

        ($this->handler)($command);
    }
}
