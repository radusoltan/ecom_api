<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\CreateLoyaltyProgram\CreateLoyaltyProgramCommand;
use App\Customer\Application\Command\CreateLoyaltyProgram\CreateLoyaltyProgramCommandHandler;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CreateLoyaltyProgramCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private CreateLoyaltyProgramCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new CreateLoyaltyProgramCommandHandler($this->repository);
    }

    public function testCreateSuccess(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD'
        );

        // Repository should check if program exists
        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->with(self::isInstanceOf(TenantId::class))
            ->willReturn(false);

        // Repository should save the new program
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $programId = $this->handler->__invoke($command);

        $this->assertIsString($programId);
        $this->assertNotEmpty($programId);
    }

    public function testCreateWithExistingProgramForTenantThrowsException(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD'
        );

        // Repository indicates program already exists
        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->with(self::isInstanceOf(TenantId::class))
            ->willReturn(true);

        // Save should not be called
        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has a loyalty program');

        $this->handler->__invoke($command);
    }

    public function testCreateWithInvalidName(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'AB', // Too short
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD'
        );

        // Repository check not needed as validation happens first
        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program name must be between 3 and 100 characters');

        $this->handler->__invoke($command);
    }

    public function testCreateWithInvalidEarningRate(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 0.0, // Invalid (must be > 0)
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD'
        );

        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earning rate must be greater than 0');

        $this->handler->__invoke($command);
    }

    public function testCreateWithZeroMinOrderValue(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 1.5,
            minOrderValue: 0, // Zero is valid (no minimum)
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD'
        );

        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $programId = $this->handler->__invoke($command);

        $this->assertIsString($programId);
    }

    public function testCreateWithHighEarningRate(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 10.0, // High but valid
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 50,
            redemptionCurrency: 'USD'
        );

        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $programId = $this->handler->__invoke($command);

        $this->assertIsString($programId);
    }

    public function testCreateSetsDefaultMinPointsToZero(): void
    {
        $tenantId = TenantId::generate();

        $command = new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 1.0,
            minOrderValue: 0,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD'
        );

        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $savedProgram = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (LoyaltyProgram $program) use (&$savedProgram) {
                $savedProgram = $program;
                return true;
            }));

        $this->handler->__invoke($command);

        $this->assertNotNull($savedProgram);
        $this->assertSame(0, $savedProgram->redemptionRule()->minPointsToRedeem());
    }
}
