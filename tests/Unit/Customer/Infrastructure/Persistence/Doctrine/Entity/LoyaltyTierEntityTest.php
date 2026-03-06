<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\LoyaltyTierEntity;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class LoyaltyTierEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $tier = LoyaltyTier::create(
            LoyaltyTierId::generate(),
            'Gold',
            1000,
            10,
            Money::fromScalars(5000, 'EUR'),
            1,
        );

        $entity = LoyaltyTierEntity::fromDomainModel($tier, 'program-001', 'tenant-001');

        self::assertSame($tier->id()->toString(), $entity->getId());
        self::assertSame('program-001', $entity->getProgramId());
        self::assertSame('tenant-001', $entity->getTenantId());
        self::assertSame('Gold', $entity->getName());
        self::assertSame(1000, $entity->getThreshold());
        self::assertSame(10, $entity->getDiscountPercentage());
        self::assertSame(5000, $entity->getFreeShippingMinOrder());
        self::assertSame('EUR', $entity->getFreeShippingCurrency());
        self::assertSame(1, $entity->getSortOrder());
        self::assertNull($entity->getProgram());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame('Gold', $restored->name());
        self::assertSame(1000, $restored->threshold());
    }

    public function testWithoutFreeShipping(): void
    {
        $tier = LoyaltyTier::create(
            LoyaltyTierId::generate(),
            'Silver',
            500,
            5,
        );

        $entity = LoyaltyTierEntity::fromDomainModel($tier, 'prog-002', 'tenant-002');
        self::assertNull($entity->getFreeShippingMinOrder());
        self::assertNull($entity->getFreeShippingCurrency());
    }

    public function testSetters(): void
    {
        $entity = new LoyaltyTierEntity();
        $entity->setId('tier-001');
        $entity->setProgramId('prog-001');
        $entity->setTenantId('tenant-001');
        $entity->setName('Platinum');
        $entity->setThreshold(5000);
        $entity->setDiscountPercentage(20);
        $entity->setFreeShippingMinOrder(3000);
        $entity->setFreeShippingCurrency('USD');
        $entity->setSortOrder(3);
        $entity->setProgram(null);
        $entity->setCreatedAt(new \DateTimeImmutable());

        self::assertSame('tier-001', $entity->getId());
        self::assertSame('prog-001', $entity->getProgramId());
        self::assertSame('Platinum', $entity->getName());
        self::assertSame(5000, $entity->getThreshold());
        self::assertSame(20, $entity->getDiscountPercentage());
        self::assertSame(3000, $entity->getFreeShippingMinOrder());
        self::assertSame('USD', $entity->getFreeShippingCurrency());
        self::assertSame(3, $entity->getSortOrder());
    }
}
