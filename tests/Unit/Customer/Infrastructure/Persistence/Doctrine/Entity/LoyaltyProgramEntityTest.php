<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\LoyaltyProgramEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class LoyaltyProgramEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $program = LoyaltyProgram::create(
            TenantId::generate(),
            'VIP Rewards',
            EarningRate::fromFloat(1.5),
            Money::fromScalars(1000, 'EUR'),
            RedemptionRule::create(0.01, 100, 5000),
        );

        $entity = LoyaltyProgramEntity::fromDomainModel($program);

        self::assertSame('VIP Rewards', $entity->getName());
        self::assertSame('1.5000', $entity->getEarningRate());
        self::assertSame(1000, $entity->getMinOrderValue());
        self::assertSame('EUR', $entity->getMinOrderCurrency());
        self::assertSame('0.0100', $entity->getConversionRate());
        self::assertSame(100, $entity->getMinPointsToRedeem());
        self::assertSame(5000, $entity->getMaxPointsPerOrder());
        self::assertTrue($entity->isActive());

        $restored = $entity->toDomainModel();
        self::assertSame('VIP Rewards', $restored->name());
    }

    public function testUpdateFromDomainModel(): void
    {
        $program = LoyaltyProgram::create(
            TenantId::generate(),
            'Basic Rewards',
            EarningRate::fromFloat(1.0),
            Money::fromScalars(500, 'USD'),
            RedemptionRule::create(0.005, 50),
        );

        $entity = LoyaltyProgramEntity::fromDomainModel($program);
        $program->deactivate();
        $entity->updateFromDomainModel($program);

        self::assertFalse($entity->isActive());
    }
}
