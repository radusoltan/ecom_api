<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateLoyaltyTier\UpdateLoyaltyTierCommand;
use App\Customer\Application\Command\UpdateLoyaltyTier\UpdateLoyaltyTierCommandHandler;
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

#[CoversClass(UpdateLoyaltyTierCommandHandler::class)]
final class UpdateLoyaltyTierCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private UpdateLoyaltyTierCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new UpdateLoyaltyTierCommandHandler($this->repository);
    }

    private function createProgram(TenantId $tenantId): LoyaltyProgram
    {
        return LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Rewards Program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('10.00', 'EUR'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );
    }

    #[Test]
    public function itUpdatesTierSuccessfully(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();
        $tierId = LoyaltyTierId::generate();

        $program = $this->createProgram($tenantId);
        $tier = LoyaltyTier::create($tierId, 'Silver', 1000, 10);
        $program->addTier($tier);

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

        $command = new UpdateLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            tierId: $tierId->toString(),
            name: 'Silver Updated',
            threshold: 1500,
            discountPercentage: 12,
            freeShippingMinOrder: null,
            freeShippingCurrency: null,
            sortOrder: 1
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itUpdatesTierWithFreeShipping(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();
        $tierId = LoyaltyTierId::generate();

        $program = $this->createProgram($tenantId);
        $tier = LoyaltyTier::create($tierId, 'Gold', 5000, 15);
        $program->addTier($tier);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new UpdateLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            tierId: $tierId->toString(),
            name: 'Gold Premium',
            threshold: 6000,
            discountPercentage: 20,
            freeShippingMinOrder: 50,
            freeShippingCurrency: 'EUR',
            sortOrder: 2
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenProgramNotFound(): void
    {
        $tenantId = TenantId::generate();
        $programId = LoyaltyProgramId::generate();
        $tierId = LoyaltyTierId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $command = new UpdateLoyaltyTierCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
            tierId: $tierId->toString(),
            name: 'Gold',
            threshold: 5000,
            discountPercentage: 15,
            freeShippingMinOrder: null,
            freeShippingCurrency: null,
            sortOrder: 1
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program with ID');

        ($this->handler)($command);
    }
}
