<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RemoveLoyaltyTier\RemoveLoyaltyTierCommand;
use App\Customer\Application\Command\RemoveLoyaltyTier\RemoveLoyaltyTierCommandHandler;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveLoyaltyTierCommandHandler::class)]
final class RemoveLoyaltyTierCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private RemoveLoyaltyTierCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new RemoveLoyaltyTierCommandHandler($this->repository);
    }

    // -------------------------------------------------------
    // Happy path
    // -------------------------------------------------------

    #[Test]
    public function itRemovesTierFromProgramSuccessfully(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();
        $tierId = LoyaltyTierId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );

        $tier = LoyaltyTier::create($tierId, 'Gold', 5000, 15);
        $program->addTier($tier);

        $command = new RemoveLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            tierId: $tierId->toString(),
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(LoyaltyProgramId::class),
                self::isInstanceOf(TenantId::class),
            )
            ->willReturn($program);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $this->handler->__invoke($command);
    }

    // -------------------------------------------------------
    // Error paths
    // -------------------------------------------------------

    #[Test]
    public function itThrowsWhenProgramNotFound(): void
    {
        $command = new RemoveLoyaltyTierCommand(
            programId: LoyaltyProgramId::generate()->toString(),
            tenantId: TenantId::generate()->toString(),
            tierId: LoyaltyTierId::generate()->toString(),
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('save');

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Loyalty program with ID');
        self::expectExceptionMessage('not found for tenant');

        $this->handler->__invoke($command);
    }

    #[Test]
    public function itThrowsWhenTierDoesNotExistInProgram(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();
        $nonExistentTierId = LoyaltyTierId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );

        // No tiers added to the program

        $command = new RemoveLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            tierId: $nonExistentTierId->toString(),
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::never())
            ->method('save');

        self::expectException(\DomainException::class);

        $this->handler->__invoke($command);
    }
}
