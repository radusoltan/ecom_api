<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PromotionEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            TenantId::generate(),
            'Summer Sale',
            PromotionType::coupon(),
            Discount::percentage(15.0),
            5,
            CouponCode::fromString('SUMMER15'),
            ['min_cart_value' => 5000],
            [],
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('+30 days'),
        );

        $entity = PromotionEntity::fromDomainModel($promotion);

        self::assertSame('Summer Sale', $entity->getName());
        self::assertSame('coupon', $entity->getType());
        self::assertSame('percentage', $entity->getDiscountType());
        self::assertSame(15.0, $entity->getDiscountValue());
        self::assertSame(5, $entity->getPriority());
        self::assertSame('SUMMER15', $entity->getCouponCode());
        self::assertFalse($entity->isActive());
        self::assertNotNull($entity->getValidFrom());
        self::assertNotNull($entity->getValidTo());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame('Summer Sale', $restored->name());
        self::assertTrue($restored->type()->equals(PromotionType::coupon()));
    }

    public function testUpdateFromDomainModel(): void
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            TenantId::generate(),
            'Flash Sale',
            PromotionType::cartRule(),
            Discount::fixedAmount(500),
        );

        $entity = PromotionEntity::fromDomainModel($promotion);
        self::assertSame('Flash Sale', $entity->getName());

        // Activate and update
        $promotion->activate();
        $entity->updateFromDomainModel($promotion);

        self::assertTrue($entity->isActive());
    }

    public function testGettersAndSetters(): void
    {
        $entity = new PromotionEntity();
        $entity->setId('test-id');
        $entity->setTenantId('tenant-id');
        $entity->setName('Test');
        $entity->setType('coupon');
        $entity->setDiscountType('percentage');
        $entity->setDiscountValue(10.0);
        $entity->setPriority(3);
        $entity->setIsActive(true);
        $entity->setCouponCode('CODE10');
        $entity->setConditions(['min' => 100]);
        $entity->setValidFrom(new \DateTimeImmutable());
        $entity->setValidTo(new \DateTimeImmutable('+7 days'));

        self::assertSame('test-id', $entity->getId());
        self::assertSame('tenant-id', $entity->getTenantId());
        self::assertSame('Test', $entity->getName());
        self::assertSame('coupon', $entity->getType());
        self::assertSame('percentage', $entity->getDiscountType());
        self::assertSame(10.0, $entity->getDiscountValue());
        self::assertSame(3, $entity->getPriority());
        self::assertTrue($entity->isActive());
        self::assertSame('CODE10', $entity->getCouponCode());
        self::assertSame(['min' => 100], $entity->getConditions());
        self::assertNotNull($entity->getValidFrom());
        self::assertNotNull($entity->getValidTo());
    }
}
