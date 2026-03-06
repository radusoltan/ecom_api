<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Application\Query\CalculateRedemption\CalculateRedemptionQuery;
use App\Customer\Application\Query\CalculateRedemption\CalculateRedemptionQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalculateRedemptionQueryHandler::class)]
final class CalculateRedemptionQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository;
    private CalculateRedemptionQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->loyaltyProgramRepository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new CalculateRedemptionQueryHandler(
            $this->customerRepository,
            $this->loyaltyProgramRepository,
        );
    }

    // -------------------------------------------------------
    // Happy path
    // -------------------------------------------------------

    #[Test]
    public function itCalculatesRedemptionPreviewSuccessfully(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('john@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );
        // Award points so customer has a balance
        $customer->awardLoyaltyPoints(500, 'initial award');

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            // 100 points => 1.00 USD discount, min 100 points
            redemptionRule: RedemptionRule::create(100.0, 100),
        );

        $query = new CalculateRedemptionQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 200,
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn($program);

        $result = $this->handler->__invoke($query);

        self::assertInstanceOf(RedeemPointsResult::class, $result);
        self::assertSame(200, $result->pointsRedeemed);
        self::assertSame(300, $result->remainingBalance);
        self::assertSame('USD', $result->discountCurrency);
        self::assertGreaterThan(0.0, $result->discountAmount);
    }

    // -------------------------------------------------------
    // Error paths
    // -------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $query = new CalculateRedemptionQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->loyaltyProgramRepository
            ->expects(self::never())
            ->method('findByTenantId');

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('not found');

        $this->handler->__invoke($query);
    }

    #[Test]
    public function itThrowsWhenLoyaltyProgramNotFound(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('john@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );
        $customer->awardLoyaltyPoints(200, 'award');

        $query = new CalculateRedemptionQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn(null);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Loyalty program not found');

        $this->handler->__invoke($query);
    }

    #[Test]
    public function itThrowsWhenLoyaltyProgramIsInactive(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('john@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );
        $customer->awardLoyaltyPoints(200, 'award');

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );
        $program->deactivate();

        $query = new CalculateRedemptionQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn($program);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('not active');

        $this->handler->__invoke($query);
    }

    #[Test]
    public function itThrowsWhenPointsToRedeemIsZero(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('john@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );
        $customer->awardLoyaltyPoints(200, 'award');

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );

        $query = new CalculateRedemptionQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 0,
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn($program);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Points to redeem must be greater than 0');

        $this->handler->__invoke($query);
    }

    #[Test]
    public function itThrowsWhenCustomerHasInsufficientPoints(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('john@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );
        // Only 50 points
        $customer->awardLoyaltyPoints(50, 'award');

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );

        $query = new CalculateRedemptionQuery(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 200,
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn($program);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Insufficient loyalty points');

        $this->handler->__invoke($query);
    }
}
