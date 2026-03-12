<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Service;

use App\Customer\Application\Command\RedeemPoints\RedeemPointsCommand;
use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Application\Query\GetCustomerLoyaltyStatus\GetCustomerLoyaltyStatusQuery;
use App\Customer\Application\Service\LoyaltyPointsService;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(LoyaltyPointsService::class)]
final class LoyaltyPointsServiceTest extends TestCase
{
    private MessageBusInterface $queryBus;
    private MessageBusInterface $commandBus;
    private CustomerRepositoryInterface $customerRepository;
    private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository;
    private LoyaltyPointsService $service;

    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->loyaltyProgramRepository = $this->createMock(LoyaltyProgramRepositoryInterface::class);

        $this->service = new LoyaltyPointsService(
            queryBus: $this->queryBus,
            commandBus: $this->commandBus,
            customerRepository: $this->customerRepository,
            loyaltyProgramRepository: $this->loyaltyProgramRepository,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();
    }

    // -----------------------------------------------------------------------
    // awardPointsForOrder
    // -----------------------------------------------------------------------

    #[Test]
    public function itAwardsPointsWhenProgramIsActiveAndOrderMeetsMinimum(): void
    {
        $customer = $this->buildCustomer();
        $program = $this->buildActiveProgram(earningRate: 1.0, minOrderAmount: '10.00');
        $orderTotal = Money::of('100.00', 'USD');

        $this->customerRepository
            ->method('findById')
            ->willReturn($customer);

        $this->loyaltyProgramRepository
            ->method('findByTenantId')
            ->willReturn($program);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $points = $this->service->awardPointsForOrder($this->customerId, $this->tenantId, $orderTotal);

        // EarningRate 1.0 * 100.0 (decimal amount) = floor(100.0) = 100 points
        self::assertSame(100, $points);
    }

    #[Test]
    public function itReturnsZeroPointsWhenOrderIsBelowMinimum(): void
    {
        $customer = $this->buildCustomer();
        $program = $this->buildActiveProgram(earningRate: 1.0, minOrderAmount: '50.00');
        $orderTotal = Money::of('10.00', 'USD');

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $this->customerRepository->expects(self::never())->method('save');

        $points = $this->service->awardPointsForOrder($this->customerId, $this->tenantId, $orderTotal);

        self::assertSame(0, $points);
    }

    #[Test]
    public function itThrowsInvalidArgumentExceptionWhenCustomerNotFoundOnAward(): void
    {
        $this->customerRepository->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->awardPointsForOrder(
            $this->customerId,
            $this->tenantId,
            Money::of('100.00', 'USD')
        );
    }

    #[Test]
    public function itThrowsDomainExceptionWhenLoyaltyProgramNotFoundOnAward(): void
    {
        $customer = $this->buildCustomer();
        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Loyalty program not found');

        $this->service->awardPointsForOrder(
            $this->customerId,
            $this->tenantId,
            Money::of('100.00', 'USD')
        );
    }

    #[Test]
    public function itThrowsDomainExceptionWhenLoyaltyProgramIsNotActiveOnAward(): void
    {
        $customer = $this->buildCustomer();
        $program = $this->buildInactiveProgram();

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Loyalty program is not active');

        $this->service->awardPointsForOrder(
            $this->customerId,
            $this->tenantId,
            Money::of('100.00', 'USD')
        );
    }

    #[Test]
    public function itDoesNotSaveCustomerWhenZeroPointsAreAwarded(): void
    {
        $customer = $this->buildCustomer();
        // Min order is higher than the order total so calculatePointsForOrder returns 0
        $program = $this->buildActiveProgram(earningRate: 1.0, minOrderAmount: '999.00');
        $orderTotal = Money::of('1.00', 'USD');

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $this->customerRepository->expects(self::never())->method('save');

        $points = $this->service->awardPointsForOrder($this->customerId, $this->tenantId, $orderTotal);

        self::assertSame(0, $points);
    }

    // -----------------------------------------------------------------------
    // redeemPoints
    // -----------------------------------------------------------------------

    #[Test]
    public function itRedeemsPointsAndReturnsResult(): void
    {
        $customer = $this->buildCustomer();
        $redeemResult = new RedeemPointsResult(
            pointsRedeemed: 500,
            discountAmountMinorUnits: 500,
            discountCurrency: 'USD',
            remainingBalance: 200,
        );

        $handledStamp = new HandledStamp($redeemResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $result = $this->service->redeemPoints($this->customerId, $this->tenantId, 500);

        self::assertSame(500, $result->pointsRedeemed);
        self::assertSame(500, $result->discountAmountMinorUnits);
        self::assertSame('USD', $result->discountCurrency);
        self::assertSame(200, $result->remainingBalance);
    }

    #[Test]
    public function itRedeemsPointsWithOrderId(): void
    {
        $customer = $this->buildCustomer();
        $redeemResult = new RedeemPointsResult(100, 100, 'USD', 0);

        $handledStamp = new HandledStamp($redeemResult, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->customerRepository->method('findById')->willReturn($customer);

        $capturedCommand = null;
        $this->commandBus
            ->method('dispatch')
            ->willReturnCallback(function (object $command) use ($envelope, &$capturedCommand) {
                $capturedCommand = $command;

                return $envelope;
            });

        $this->service->redeemPoints($this->customerId, $this->tenantId, 100, 'order-123');

        self::assertInstanceOf(RedeemPointsCommand::class, $capturedCommand);
        self::assertSame('order-123', $capturedCommand->orderId);
        self::assertSame(100, $capturedCommand->pointsToRedeem);
    }

    #[Test]
    public function itThrowsInvalidArgumentExceptionWhenCustomerNotFoundOnRedeem(): void
    {
        $this->customerRepository->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->redeemPoints($this->customerId, $this->tenantId, 100);
    }

    #[Test]
    public function itThrowsRuntimeExceptionWhenRedeemCommandNotHandled(): void
    {
        $customer = $this->buildCustomer();
        // Envelope with no HandledStamp
        $envelope = new Envelope(new \stdClass());

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->commandBus->method('dispatch')->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command was not handled');

        $this->service->redeemPoints($this->customerId, $this->tenantId, 100);
    }

    // -----------------------------------------------------------------------
    // calculatePointsToEarn
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesPointsToEarnForActiveProgram(): void
    {
        $program = $this->buildActiveProgram(earningRate: 2.0, minOrderAmount: '0.00');
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $orderTotal = Money::of('50.00', 'USD');
        $points = $this->service->calculatePointsToEarn($orderTotal, $this->tenantId);

        // 2.0 rate * 50.00 dollars = 100 points
        self::assertSame(100, $points);
    }

    #[Test]
    public function itReturnsZeroWhenNoProgramExists(): void
    {
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn(null);

        $points = $this->service->calculatePointsToEarn(Money::of('100.00', 'USD'), $this->tenantId);

        self::assertSame(0, $points);
    }

    #[Test]
    public function itReturnsZeroWhenProgramIsInactive(): void
    {
        $program = $this->buildInactiveProgram();
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $points = $this->service->calculatePointsToEarn(Money::of('100.00', 'USD'), $this->tenantId);

        self::assertSame(0, $points);
    }

    #[Test]
    public function itReturnsZeroWhenOrderBelowMinForCalculation(): void
    {
        $program = $this->buildActiveProgram(earningRate: 1.0, minOrderAmount: '100.00');
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $points = $this->service->calculatePointsToEarn(Money::of('50.00', 'USD'), $this->tenantId);

        self::assertSame(0, $points);
    }

    // -----------------------------------------------------------------------
    // getCustomerStatus
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsCustomerLoyaltyStatus(): void
    {
        $customer = $this->buildCustomer();
        $loyaltyStatus = new CustomerLoyaltyStatusDTO(
            customerId: $this->customerId->toString(),
            currentPoints: 750,
            currentTier: 'Silver',
            nextTier: 'Gold',
            pointsToNextTier: 250,
            lifetimePointsEarned: 2000,
            lifetimePointsRedeemed: 1250,
            currentTierDiscount: 5,
            programName: 'Test Rewards',
            programIsActive: true,
        );

        $handledStamp = new HandledStamp($loyaltyStatus, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $result = $this->service->getCustomerStatus($this->customerId, $this->tenantId);

        self::assertSame($this->customerId->toString(), $result->customerId);
        self::assertSame(750, $result->currentPoints);
        self::assertSame('Silver', $result->currentTier);
        self::assertSame('Gold', $result->nextTier);
        self::assertSame(250, $result->pointsToNextTier);
        self::assertTrue($result->programIsActive);
    }

    #[Test]
    public function itDispatchesGetCustomerLoyaltyStatusQueryWithCorrectData(): void
    {
        $customer = $this->buildCustomer();
        $loyaltyStatus = new CustomerLoyaltyStatusDTO(
            $this->customerId->toString(), 0, null, null, null,
            0, 0, null, null, false,
        );

        $handledStamp = new HandledStamp($loyaltyStatus, 'handler');
        $envelope = new Envelope(new \stdClass(), [$handledStamp]);

        $this->customerRepository->method('findById')->willReturn($customer);

        $capturedQuery = null;
        $this->queryBus
            ->method('dispatch')
            ->willReturnCallback(function (object $query) use ($envelope, &$capturedQuery) {
                $capturedQuery = $query;

                return $envelope;
            });

        $this->service->getCustomerStatus($this->customerId, $this->tenantId);

        self::assertInstanceOf(GetCustomerLoyaltyStatusQuery::class, $capturedQuery);
        self::assertSame($this->customerId->toString(), $capturedQuery->customerId);
        self::assertSame($this->tenantId->toString(), $capturedQuery->tenantId);
    }

    #[Test]
    public function itThrowsInvalidArgumentExceptionWhenCustomerNotFoundOnStatusQuery(): void
    {
        $this->customerRepository->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->getCustomerStatus($this->customerId, $this->tenantId);
    }

    #[Test]
    public function itThrowsRuntimeExceptionWhenStatusQueryNotHandled(): void
    {
        $customer = $this->buildCustomer();
        $envelope = new Envelope(new \stdClass());

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->queryBus->method('dispatch')->willReturn($envelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query was not handled');

        $this->service->getCustomerStatus($this->customerId, $this->tenantId);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildCustomer(): Customer
    {
        return Customer::register(
            id: $this->customerId,
            tenantId: $this->tenantId,
            email: Email::fromString('loyalty@example.com'),
            firstName: 'Loyalty',
            lastName: 'Tester',
        );
    }

    private function buildActiveProgram(float $earningRate = 1.0, string $minOrderAmount = '0.00'): LoyaltyProgram
    {
        return LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'Test Loyalty Program',
            earningRate: EarningRate::fromFloat($earningRate),
            minOrderValue: Money::of($minOrderAmount, 'USD'),
            redemptionRule: RedemptionRule::create(conversionRate: 100.0, minPointsToRedeem: 0),
        );
    }

    private function buildInactiveProgram(): LoyaltyProgram
    {
        $program = $this->buildActiveProgram();
        $program->deactivate();
        // Drain events so they don't interfere
        $program->popEvents();

        return $program;
    }
}
