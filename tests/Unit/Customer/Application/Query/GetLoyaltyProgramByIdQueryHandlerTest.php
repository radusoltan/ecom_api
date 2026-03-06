<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQuery;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQueryHandler;
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

#[CoversClass(GetLoyaltyProgramByIdQueryHandler::class)]
final class GetLoyaltyProgramByIdQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private LoyaltyProgramRepositoryInterface&MockObject $loyaltyProgramRepository;
    private GetLoyaltyProgramByIdQueryHandler $handler;

    protected function setUp(): void
    {
        $this->loyaltyProgramRepository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new GetLoyaltyProgramByIdQueryHandler($this->loyaltyProgramRepository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeProgram(string $name = 'Gold Rewards'): LoyaltyProgram
    {
        $program = LoyaltyProgram::create(
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: $name,
            earningRate: EarningRate::fromFloat(1.5),
            minOrderValue: Money::of('50.00', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );
        $program->popEvents();

        return $program;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsLoyaltyProgramDTOWhenFound(): void
    {
        $program = $this->makeProgram();

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(LoyaltyProgramId::class),
                self::isInstanceOf(TenantId::class),
            )
            ->willReturn($program);

        $query = new GetLoyaltyProgramByIdQuery(
            programId: $program->id()->toString(),
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(LoyaltyProgramDTO::class, $result);
        self::assertSame('Gold Rewards', $result->name);
        self::assertSame(1.5, $result->earningRate);
        self::assertTrue($result->isActive);
    }

    #[Test]
    public function itMapsAllDTOFieldsCorrectly(): void
    {
        $program = $this->makeProgram('Platinum Club');

        $this->loyaltyProgramRepository->method('findById')->willReturn($program);

        $query = new GetLoyaltyProgramByIdQuery(
            programId: $program->id()->toString(),
            tenantId: self::TENANT_ID,
        );
        $result = ($this->handler)($query);

        self::assertSame($program->id()->toString(), $result->id);
        self::assertSame(self::TENANT_ID, $result->tenantId);
        self::assertSame('Platinum Club', $result->name);
        self::assertNull($result->description);
        self::assertIsArray($result->minOrderValue);
        self::assertIsArray($result->redemptionRule);
        self::assertNull($result->validityDays);
        self::assertIsArray($result->tiers);
        self::assertEmpty($result->tiers);
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenProgramNotFound(): void
    {
        $this->loyaltyProgramRepository
            ->method('findById')
            ->willReturn(null);

        $query = new GetLoyaltyProgramByIdQuery(
            programId: LoyaltyProgramId::generate()->toString(),
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    // -----------------------------------------------------------------------
    // Repository called once
    // -----------------------------------------------------------------------

    #[Test]
    public function itCallsRepositoryExactlyOnce(): void
    {
        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $query = new GetLoyaltyProgramByIdQuery(
            programId: LoyaltyProgramId::generate()->toString(),
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($query);
    }
}
