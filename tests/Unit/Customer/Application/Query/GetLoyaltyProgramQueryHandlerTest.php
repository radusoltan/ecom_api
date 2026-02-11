<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\Query\GetLoyaltyProgram\GetLoyaltyProgramQuery;
use App\Customer\Application\Query\GetLoyaltyProgram\GetLoyaltyProgramQueryHandler;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetLoyaltyProgramQueryHandlerTest extends TestCase
{
    private LoyaltyProgramRepositoryInterface $repository;
    private GetLoyaltyProgramQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->handler = new GetLoyaltyProgramQueryHandler($this->repository);
    }

    public function testGetProgramSuccess(): void
    {
        $tenantId = TenantId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.5),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $query = new GetLoyaltyProgramQuery($tenantId->toString());

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with(self::isInstanceOf(TenantId::class))
            ->willReturn($program);

        $result = $this->handler->__invoke($query);

        $this->assertInstanceOf(LoyaltyProgramDTO::class, $result);
        $this->assertSame('VIP Rewards', $result->name);
        $this->assertSame(1.5, $result->earningRate);
        $this->assertTrue($result->isActive);
    }

    public function testGetProgramNotFound(): void
    {
        $tenantId = TenantId::generate();
        $query = new GetLoyaltyProgramQuery($tenantId->toString());

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with(self::isInstanceOf(TenantId::class))
            ->willReturn(null);

        $result = $this->handler->__invoke($query);

        $this->assertNull($result);
    }

    public function testGetProgramReturnsDTO(): void
    {
        $tenantId = TenantId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Premium Rewards',
            earningRate: EarningRate::fromFloat(2.0),
            minOrderValue: Money::of('100', 'USD'),
            redemptionRule: RedemptionRule::create(50.0, 250, 5000)
        );

        $query = new GetLoyaltyProgramQuery($tenantId->toString());

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn($program);

        $result = $this->handler->__invoke($query);

        $this->assertInstanceOf(LoyaltyProgramDTO::class, $result);
        $this->assertSame($program->id()->toString(), $result->id);
        $this->assertSame($tenantId->toString(), $result->tenantId);
        $this->assertSame('Premium Rewards', $result->name);
        $this->assertNull($result->description);
        $this->assertSame(2.0, $result->earningRate);
        $this->assertIsArray($result->minOrderValue);
        $this->assertIsArray($result->redemptionRule);
        $this->assertNull($result->validityDays);
        $this->assertTrue($result->isActive);
        $this->assertIsArray($result->tiers);
        $this->assertEmpty($result->tiers);
    }

    public function testGetProgramWithDescription(): void
    {
        $tenantId = TenantId::generate();

        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            redemptionRule: RedemptionRule::create(100.0, 100)
        );

        $program->update(
            name: 'VIP Rewards',
            description: 'Our premium loyalty program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('50', 'USD'),
            validityDays: 365
        );

        $query = new GetLoyaltyProgramQuery($tenantId->toString());

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn($program);

        $result = $this->handler->__invoke($query);

        $this->assertNotNull($result);
        $this->assertSame('Our premium loyalty program', $result->description);
        $this->assertSame(365, $result->validityDays);
    }
}
