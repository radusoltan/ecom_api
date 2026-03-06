<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class FlashSaleEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $tenantId = TenantId::generate();
        $productId = ProductId::generate();

        $flashSale = FlashSale::create(
            $flashSaleId, $tenantId, 'Black Friday',
            [$productId], Discount::percentage(30.0),
            new \DateTimeImmutable('+1 hour'), new \DateTimeImmutable('+25 hours'),
        );

        $entity = FlashSaleEntity::fromDomainModel($flashSale);

        self::assertSame($flashSaleId->toString(), $entity->getId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame('Black Friday', $entity->getName());
        self::assertSame('scheduled', $entity->getStatus());
        self::assertSame('percentage', $entity->getDiscountType());
        self::assertSame(30.0, $entity->getDiscountValue());
        self::assertCount(1, $entity->getProductIds());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($flashSaleId));
        self::assertSame('Black Friday', $restored->name());
        self::assertTrue($restored->isScheduled());
    }

    public function testUpdateFromDomainModel(): void
    {
        $flashSale = FlashSale::create(
            FlashSaleId::generate(), TenantId::generate(), 'Sale',
            [ProductId::generate()], Discount::percentage(10.0),
            new \DateTimeImmutable('-1 hour'), new \DateTimeImmutable('+23 hours'),
        );

        $entity = FlashSaleEntity::fromDomainModel($flashSale);
        self::assertSame('scheduled', $entity->getStatus());

        $flashSale->activate();
        $entity->updateFromDomainModel($flashSale);

        self::assertSame('active', $entity->getStatus());
    }
}
