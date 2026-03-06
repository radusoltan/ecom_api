<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\ActivateLoyaltyProgram\ActivateLoyaltyProgramCommand;
use App\Customer\Application\Command\ActivateLoyaltyProgram\ActivateLoyaltyProgramCommandHandler;
use App\Customer\Domain\Event\LoyaltyProgramActivated;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivateLoyaltyProgramCommandHandler::class)]
final class ActivateLoyaltyProgramCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface&MockObject $repository;
    private ActivateLoyaltyProgramCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new ActivateLoyaltyProgramCommandHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Happy path: activates an inactive program
    // -----------------------------------------------------------------------

    #[Test]
    public function itActivatesInactiveLoyaltyProgram(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $program = $this->buildInactiveProgram($programId, $tenantId);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (LoyaltyProgramId $id) => $id->toString() === $programId->toString()),
                self::callback(fn (TenantId $tid) => $tid->equals($tenantId))
            )
            ->willReturn($program);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (LoyaltyProgram $p) => $p->isActive()));

        $command = new ActivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
        );

        ($this->handler)($command);

        self::assertTrue($program->isActive());
    }

    // -----------------------------------------------------------------------
    // Domain event is recorded on activation
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsLoyaltyProgramActivatedEvent(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $program = $this->buildInactiveProgram($programId, $tenantId);

        $this->repository->method('findById')->willReturn($program);
        $this->repository->method('save');

        $command = new ActivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
        );

        ($this->handler)($command);

        $events = $program->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramActivated::class, $events[0]);
    }

    // -----------------------------------------------------------------------
    // Exception: program not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenProgramNotFound(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');

        $command = new ActivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Exception: already active
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenProgramIsAlreadyActive(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        // LoyaltyProgram::create() starts as active
        $activeProgram = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Active Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('0.00', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 50, null),
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($activeProgram);

        $this->repository->expects(self::never())->method('save');

        $command = new ActivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already active');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Repository receives the updated program
    // -----------------------------------------------------------------------

    #[Test]
    public function itPassesActivatedProgramToRepository(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $program = $this->buildInactiveProgram($programId, $tenantId);

        $this->repository->method('findById')->willReturn($program);

        $savedProgram = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (LoyaltyProgram $p) use (&$savedProgram): void {
                $savedProgram = $p;
            });

        $command = new ActivateLoyaltyProgramCommand(
            programId: $programId->toString(),
            tenantId: $tenantId->toString(),
        );

        ($this->handler)($command);

        self::assertNotNull($savedProgram);
        self::assertTrue($savedProgram->isActive());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildInactiveProgram(LoyaltyProgramId $id, TenantId $tenantId): LoyaltyProgram
    {
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Test Rewards',
            earningRate: EarningRate::fromFloat(1.5),
            minOrderValue: Money::of('10.00', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 50, null),
        );

        // Deactivate so it can be activated by the handler
        $program->deactivate();

        // Drain events from setup operations
        $program->popEvents();

        return $program;
    }
}
