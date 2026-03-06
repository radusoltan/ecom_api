<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\CreateLoyaltyProgram\CreateLoyaltyProgramCommand;
use App\Customer\Application\Command\CreateLoyaltyProgram\CreateLoyaltyProgramCommandHandler;
use App\Customer\Domain\Event\LoyaltyProgramCreated;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateLoyaltyProgramCommandHandler::class)]
final class CreateLoyaltyProgramCommandHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface&MockObject $repository;
    private CreateLoyaltyProgramCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new CreateLoyaltyProgramCommandHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Happy path: program created, ID returned
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesLoyaltyProgramAndReturnsId(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->with(self::callback(fn (TenantId $id) => $id->equals($tenantId)))
            ->willReturn(false);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(LoyaltyProgram::class));

        $programId = ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'VIP Rewards',
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));

        self::assertIsString($programId);
        self::assertNotEmpty($programId);
    }

    // -----------------------------------------------------------------------
    // Saved program has correct properties
    // -----------------------------------------------------------------------

    #[Test]
    public function itSavesProgramWithCorrectName(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('exists')->willReturn(false);

        $savedProgram = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (LoyaltyProgram $p) use (&$savedProgram): void {
                $savedProgram = $p;
            });

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'Gold Program',
            earningRate: 2.0,
            minOrderValue: 0,
            minOrderCurrency: 'EUR',
            redemptionRate: 50,
            redemptionCurrency: 'EUR',
        ));

        self::assertNotNull($savedProgram);
        self::assertSame('Gold Program', $savedProgram->name());
        self::assertTrue($savedProgram->isActive());
    }

    // -----------------------------------------------------------------------
    // Program starts active
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesProgramInActiveState(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->repository->method('exists')->willReturn(false);

        $savedProgram = null;
        $this->repository
            ->method('save')
            ->willReturnCallback(static function (LoyaltyProgram $p) use (&$savedProgram): void {
                $savedProgram = $p;
            });

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'Active Program',
            earningRate: 1.0,
            minOrderValue: 0,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));

        self::assertNotNull($savedProgram);
        self::assertTrue($savedProgram->isActive());
    }

    // -----------------------------------------------------------------------
    // Domain event is recorded
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsLoyaltyProgramCreatedEvent(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->repository->method('exists')->willReturn(false);

        $savedProgram = null;
        $this->repository
            ->method('save')
            ->willReturnCallback(static function (LoyaltyProgram $p) use (&$savedProgram): void {
                $savedProgram = $p;
            });

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'Event Program',
            earningRate: 1.0,
            minOrderValue: 0,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));

        self::assertNotNull($savedProgram);
        $events = $savedProgram->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyProgramCreated::class, $events[0]);
    }

    // -----------------------------------------------------------------------
    // Exception: program already exists for tenant
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantAlreadyHasLoyaltyProgram(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository
            ->expects(self::once())
            ->method('exists')
            ->willReturn(true);

        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has a loyalty program');

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'Duplicate Program',
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));
    }

    // -----------------------------------------------------------------------
    // Exception: name too short
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenNameIsTooShort(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('exists')->willReturn(false);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty program name must be between 3 and 100 characters');

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'AB',
            earningRate: 1.5,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));
    }

    // -----------------------------------------------------------------------
    // Exception: earning rate is zero
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenEarningRateIsZero(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('exists')->willReturn(false);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earning rate must be greater than 0');

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'Zero Rate Program',
            earningRate: 0.0,
            minOrderValue: 50,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));
    }

    // -----------------------------------------------------------------------
    // Zero min order value is valid
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsZeroMinOrderValue(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('exists')->willReturn(false);
        $this->repository->expects(self::once())->method('save');

        $programId = ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'No Minimum Program',
            earningRate: 1.5,
            minOrderValue: 0,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));

        self::assertIsString($programId);
    }

    // -----------------------------------------------------------------------
    // RedemptionRule defaults to 0 min points
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsMinPointsToZeroByDefault(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('exists')->willReturn(false);

        $savedProgram = null;
        $this->repository
            ->method('save')
            ->willReturnCallback(static function (LoyaltyProgram $p) use (&$savedProgram): void {
                $savedProgram = $p;
            });

        ($this->handler)(new CreateLoyaltyProgramCommand(
            tenantId: $tenantId->toString(),
            name: 'Standard Rewards',
            earningRate: 1.0,
            minOrderValue: 0,
            minOrderCurrency: 'USD',
            redemptionRate: 100,
            redemptionCurrency: 'USD',
        ));

        self::assertNotNull($savedProgram);
        self::assertSame(0, $savedProgram->redemptionRule()->minPointsToRedeem());
    }
}
