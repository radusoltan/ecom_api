<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RedeemPoints\RedeemPointsCommand;
use App\Customer\Application\Command\RedeemPoints\RedeemPointsCommandHandler;
use App\Customer\Application\DTO\RedeemPointsResult;
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

#[CoversClass(RedeemPointsCommandHandler::class)]
final class RedeemPointsCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository;
    private RedeemPointsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->loyaltyProgramRepository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new RedeemPointsCommandHandler(
            $this->customerRepository,
            $this->loyaltyProgramRepository
        );
    }

    // ---------------------------------------------------------------------------
    // Exception: customer not found
    // ---------------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->loyaltyProgramRepository->expects(self::never())->method('findByTenantId');

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Exception: loyalty program not found
    // ---------------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenLoyaltyProgramNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId, 500);

        $this->customerRepository
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn(null);

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Loyalty program not found');

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Exception: loyalty program inactive
    // ---------------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenLoyaltyProgramIsInactive(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId, 500);
        $program = $this->buildLoyaltyProgram($tenantId, active: false);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not active');

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Exception: zero or negative points to redeem
    // ---------------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPointsToRedeemIsZero(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId, 500);
        $program = $this->buildLoyaltyProgram($tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 0,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than 0');

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Exception: insufficient points
    // ---------------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerHasInsufficientPoints(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId, 50);
        $program = $this->buildLoyaltyProgram($tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 200,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot redeem points');

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Happy path: successful redemption
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRedeemsPointsSuccessfully(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId, 500);
        // 100 points / 100 conversion rate = $1.00 discount
        $program = $this->buildLoyaltyProgram($tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);
        $this->customerRepository->expects(self::once())->method('save');

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $result = ($this->handler)($command);

        self::assertInstanceOf(RedeemPointsResult::class, $result);
        self::assertSame(100, $result->pointsRedeemed);
        self::assertSame(400, $result->remainingBalance);
        self::assertSame('USD', $result->discountCurrency);
        self::assertGreaterThan(0, $result->discountAmount);
    }

    // ---------------------------------------------------------------------------
    // Result contains correct discount amount
    // ---------------------------------------------------------------------------

    #[Test]
    public function itReturnsCorrectDiscountAmount(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId, 1000);
        // 100 points / 100 conversion rate = 1.00 USD discount
        $program = $this->buildLoyaltyProgram($tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);
        $this->customerRepository->method('save');

        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: 100,
        );

        $result = ($this->handler)($command);

        // 100 points / 100 rate = 1.00
        self::assertEqualsWithDelta(1.00, $result->discountAmount, 0.001);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function buildCustomer(CustomerId $id, TenantId $tenantId, int $initialPoints = 0): Customer
    {
        $customer = Customer::register(
            $id,
            $tenantId,
            Email::fromString('user@example.com'),
            'John',
            'Doe'
        );

        if ($initialPoints > 0) {
            $customer->awardLoyaltyPoints($initialPoints, 'Initial award');
        }

        return $customer;
    }

    private function buildLoyaltyProgram(TenantId $tenantId, bool $active = true): LoyaltyProgram
    {
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Test Program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('0.00', 'EUR'),
            redemptionRule: RedemptionRule::create(
                conversionRate: 100.0,
                minPointsToRedeem: 50,
                maxPointsPerOrder: null
            )
        );

        if (!$active) {
            $program->deactivate();
        }

        return $program;
    }
}
