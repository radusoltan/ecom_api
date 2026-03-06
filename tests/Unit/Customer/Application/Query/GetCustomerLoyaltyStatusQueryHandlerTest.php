<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Application\Query\GetCustomerLoyaltyStatus\GetCustomerLoyaltyStatusQuery;
use App\Customer\Application\Query\GetCustomerLoyaltyStatus\GetCustomerLoyaltyStatusQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerLoyaltyStatusQueryHandler::class)]
final class GetCustomerLoyaltyStatusQueryHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository;
    private GetCustomerLoyaltyStatusQueryHandler $handler;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->loyaltyProgramRepository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new GetCustomerLoyaltyStatusQueryHandler(
            $this->customerRepository,
            $this->loyaltyProgramRepository,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();

        $this->customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            Email::fromString('loyalty-status@example.com'),
            'Grace',
            'Hopper',
        );
        $this->customer->pullDomainEvents();
    }

    // ---------------------------------------------------------------
    // No loyalty program
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsBasicStatusWhenNoProgramExists(): void
    {
        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($this->customer);

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn(null);

        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerLoyaltyStatusDTO::class, $result);
        self::assertSame($this->customerId->toString(), $result->customerId);
        self::assertSame(0, $result->currentPoints);
        self::assertNull($result->currentTier);
        self::assertNull($result->nextTier);
        self::assertNull($result->pointsToNextTier);
        self::assertFalse($result->programIsActive);
        self::assertNull($result->programName);
    }

    // ---------------------------------------------------------------
    // With active loyalty program, no tiers
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsStatusWithProgramButNoTiers(): void
    {
        $program = $this->buildProgram();
        $program->popEvents();

        $this->customerRepository->method('findById')->willReturn($this->customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerLoyaltyStatusDTO::class, $result);
        self::assertNull($result->currentTier);
        self::assertNull($result->nextTier);
        self::assertTrue($result->programIsActive);
        self::assertSame('Gold Program', $result->programName);
    }

    // ---------------------------------------------------------------
    // With tiers — customer in mid tier
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsTierAndNextTierWhenCustomerHasPoints(): void
    {
        // Give customer 500 points so they are in the Silver tier (threshold 200)
        $this->customer->awardLoyaltyPoints(500, 'Test award');
        $this->customer->pullDomainEvents();

        $program = $this->buildProgram();
        $this->addTiers($program);
        $program->popEvents();

        $this->customerRepository->method('findById')->willReturn($this->customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
        );

        $result = ($this->handler)($query);

        self::assertSame(500, $result->currentPoints);
        self::assertSame('Silver', $result->currentTier);
        // Next tier is Gold (threshold 1000)
        self::assertSame('Gold', $result->nextTier);
        self::assertSame(500, $result->pointsToNextTier);
    }

    #[Test]
    public function itReturnsNoNextTierWhenCustomerIsAtHighestTier(): void
    {
        // Award enough points to be in Gold tier (threshold 1000)
        $this->customer->awardLoyaltyPoints(1500, 'Test award');
        $this->customer->pullDomainEvents();

        $program = $this->buildProgram();
        $this->addTiers($program);
        $program->popEvents();

        $this->customerRepository->method('findById')->willReturn($this->customer);
        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
        );

        $result = ($this->handler)($query);

        self::assertSame(1500, $result->currentPoints);
        self::assertSame('Gold', $result->currentTier);
        self::assertNull($result->nextTier);
        self::assertNull($result->pointsToNextTier);
    }

    // ---------------------------------------------------------------
    // Error path
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Customer with ID ".*" not found/');

        $query = new GetCustomerLoyaltyStatusQuery(
            customerId: $this->customerId->toString(),
            tenantId: $this->tenantId->toString(),
        );

        ($this->handler)($query);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildProgram(): LoyaltyProgram
    {
        return LoyaltyProgram::create(
            tenantId: $this->tenantId,
            name: 'Gold Program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('0', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );
    }

    private function addTiers(LoyaltyProgram $program): void
    {
        $bronzeTier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 0,
            sortOrder: 1,
        );

        $silverTier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Silver',
            threshold: 200,
            discountPercentage: 5,
            sortOrder: 2,
        );

        $goldTier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Gold',
            threshold: 1000,
            discountPercentage: 10,
            sortOrder: 3,
        );

        $program->addTier($bronzeTier);
        $program->addTier($silverTier);
        $program->addTier($goldTier);
        $program->popEvents();
    }
}
