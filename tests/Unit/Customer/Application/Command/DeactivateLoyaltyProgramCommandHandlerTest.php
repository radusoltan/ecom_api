<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\DeactivateLoyaltyProgram\DeactivateLoyaltyProgramCommand;
use App\Customer\Application\Command\DeactivateLoyaltyProgram\DeactivateLoyaltyProgramCommandHandler;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeactivateLoyaltyProgramCommandHandler::class)]
final class DeactivateLoyaltyProgramCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private DeactivateLoyaltyProgramCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new DeactivateLoyaltyProgramCommandHandler($this->repository);
    }

    private function createActiveProgram(LoyaltyProgramId $id, TenantId $tenantId): LoyaltyProgram
    {
        return LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Test Program',
            earningRate: EarningRate::fromFloat(1.5),
            minOrderValue: Money::of('10.00', 'EUR'),
            redemptionRule: RedemptionRule::create(100.0, 50, 1000),
        );
    }

    #[Test]
    public function itDeactivatesActiveLoyaltyProgram(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::generate();

        $program = $this->createActiveProgram($programId, $tenantId);

        $command = new DeactivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($program);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($this->callback(fn (LoyaltyProgram $p) => !$p->isActive()));

        ($this->handler)($command);

        self::assertFalse($program->isActive());
    }

    #[Test]
    public function itThrowsWhenProgramNotFound(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::generate();

        $command = new DeactivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString()
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
