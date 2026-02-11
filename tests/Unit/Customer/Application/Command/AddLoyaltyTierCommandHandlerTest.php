<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\AddLoyaltyTier\AddLoyaltyTierCommand;
use App\Customer\Application\Command\AddLoyaltyTier\AddLoyaltyTierCommandHandler;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class AddLoyaltyTierCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private AddLoyaltyTierCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new AddLoyaltyTierCommandHandler($this->repository);
    }

    public function testAddTierSuccess(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $command = new AddLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'Gold',
            threshold: 5000,
            discountPercentage: 15,
            freeShippingMinOrder: 50,
            freeShippingCurrency: 'USD',
            sortOrder: 2
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(LoyaltyProgramId::class),
                self::isInstanceOf(TenantId::class)
            )
            ->willReturn($program);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $this->handler->__invoke($command);
    }

    public function testAddTierProgramNotFound(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $command = new AddLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'Gold',
            threshold: 5000,
            discountPercentage: 15,
            freeShippingMinOrder: null,
            freeShippingCurrency: null,
            sortOrder: 2
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program with ID');
        $this->expectExceptionMessage('not found for tenant');

        $this->handler->__invoke($command);
    }

    public function testAddTierDuplicateName(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        // Add existing tier
        $existingTier = LoyaltyTier::create(
            LoyaltyTierId::generate(),
            'Gold',
            5000,
            15
        );
        $program->addTier($existingTier);

        $command = new AddLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'Gold', // Duplicate name
            threshold: 10000,
            discountPercentage: 20,
            freeShippingMinOrder: null,
            freeShippingCurrency: null,
            sortOrder: 3
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tier with name "Gold" already exists');

        $this->handler->__invoke($command);
    }

    public function testAddTierWithoutFreeShipping(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $command = new AddLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 5,
            freeShippingMinOrder: null, // No free shipping
            freeShippingCurrency: null,
            sortOrder: 0
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $this->handler->__invoke($command);
    }

    public function testAddTierWithInvalidName(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $command = new AddLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'AB', // Too short
            threshold: 0,
            discountPercentage: 5,
            freeShippingMinOrder: null,
            freeShippingCurrency: null,
            sortOrder: 0
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier name must be between 3 and 50 characters');

        $this->handler->__invoke($command);
    }

    public function testAddTierWithInvalidDiscountPercentage(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $command = new AddLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'Gold',
            threshold: 5000,
            discountPercentage: 101, // Invalid (> 100)
            freeShippingMinOrder: null,
            freeShippingCurrency: null,
            sortOrder: 2
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        $this->handler->__invoke($command);
    }
}
