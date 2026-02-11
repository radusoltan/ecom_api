<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateLoyaltyProgram\UpdateLoyaltyProgramCommand;
use App\Customer\Application\Command\UpdateLoyaltyProgram\UpdateLoyaltyProgramCommandHandler;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class UpdateLoyaltyProgramCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private UpdateLoyaltyProgramCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new UpdateLoyaltyProgramCommandHandler($this->repository);
    }

    public function testUpdateSuccess(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        // Create existing program
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Old Name',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'New Name',
            description: 'Updated description',
            earningRate: 2.0,
            minOrderValue: 100,
            minOrderCurrency: 'USD',
            validityDays: 365
        );

        // Repository should find existing program
        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(LoyaltyProgramId::class),
                self::isInstanceOf(TenantId::class)
            )
            ->willReturn($program);

        // Repository should save updated program
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $this->handler->__invoke($command);
    }

    public function testUpdateProgramNotFound(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();

        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'New Name',
            description: null,
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            validityDays: null
        );

        // Repository returns null (not found)
        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        // Save should not be called
        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program with ID');
        $this->expectExceptionMessage('not found for tenant');

        $this->handler->__invoke($command);
    }

    public function testUpdateWithInvalidValidityDays(): void
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

        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            description: null,
            earningRate: 1.0,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            validityDays: 0 // Invalid
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validity days must be greater than 0 or null');

        $this->handler->__invoke($command);
    }

    public function testUpdateWithNullDescription(): void
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

        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'Updated Name',
            description: null, // Null description is valid
            earningRate: 1.5,
            minOrderValue: 75,
            minOrderCurrency: 'USD',
            validityDays: 180
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

    public function testUpdateWithInvalidEarningRate(): void
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

        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            description: null,
            earningRate: 0.0, // Invalid
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            validityDays: null
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earning rate must be greater than 0');

        $this->handler->__invoke($command);
    }

    public function testUpdateWithTooLongDescription(): void
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

        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            description: str_repeat('A', 501), // Too long
            earningRate: 1.0,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            validityDays: null
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program description cannot exceed 500 characters');

        $this->handler->__invoke($command);
    }
}
