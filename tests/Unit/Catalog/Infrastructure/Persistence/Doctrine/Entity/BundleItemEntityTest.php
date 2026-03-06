<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\BundleItemEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class BundleItemEntityTest extends TestCase
{
    private ProductId $bundleProductId;
    private ProductId $productId;
    private TenantId $tenantId;
    private string $entityId;

    protected function setUp(): void
    {
        $this->bundleProductId = ProductId::fromString('00000000-0000-4000-8000-000000000020');
        $this->productId = ProductId::fromString('00000000-0000-4000-8000-000000000030');
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->entityId = '00000000-0000-4000-8000-000000000099';
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $bundleItem = BundleItem::create(
            $this->productId,
            2,
            Money::fromScalars(1999, 'EUR')
        );

        $entity = BundleItemEntity::fromDomainModel(
            $bundleItem,
            $this->bundleProductId,
            $this->tenantId,
            $this->entityId
        );

        self::assertSame($this->entityId, $entity->getId());
        self::assertSame($this->tenantId->toString(), $entity->getTenantId());
        self::assertSame($this->bundleProductId->toString(), $entity->getBundleProductId());
        self::assertSame($this->productId->toString(), $entity->getProductId());
        self::assertSame(2, $entity->getQuantity());
        self::assertSame(1999, $entity->getPriceAmount());
        self::assertSame('EUR', $entity->getPriceCurrency());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        $restored = $entity->toDomainModel();
        self::assertSame($this->productId->toString(), $restored->productId()->toString());
        self::assertSame(2, $restored->quantity());
        self::assertSame(1999, $restored->price()->getAmount());
        self::assertSame('EUR', $restored->price()->getCurrency()->getCurrencyCode());
    }

    public function testFromDomainModelWithUsdCurrency(): void
    {
        $bundleItem = BundleItem::create(
            $this->productId,
            1,
            Money::fromScalars(4999, 'USD')
        );

        $entity = BundleItemEntity::fromDomainModel(
            $bundleItem,
            $this->bundleProductId,
            $this->tenantId,
            $this->entityId
        );

        self::assertSame(4999, $entity->getPriceAmount());
        self::assertSame('USD', $entity->getPriceCurrency());
        self::assertSame(1, $entity->getQuantity());

        $restored = $entity->toDomainModel();
        self::assertSame(4999, $restored->price()->getAmount());
        self::assertSame('USD', $restored->price()->getCurrency()->getCurrencyCode());
    }

    public function testFromDomainModelWithQuantityFive(): void
    {
        $bundleItem = BundleItem::create(
            $this->productId,
            5,
            Money::fromScalars(999, 'GBP')
        );

        $entity = BundleItemEntity::fromDomainModel(
            $bundleItem,
            $this->bundleProductId,
            $this->tenantId,
            $this->entityId
        );

        self::assertSame(5, $entity->getQuantity());

        $restored = $entity->toDomainModel();
        self::assertSame(5, $restored->quantity());
    }

    public function testSetters(): void
    {
        $entity = new BundleItemEntity();
        $newUpdatedAt = new \DateTimeImmutable('2026-03-01 12:00:00');

        $entity->setId('custom-id-123');
        $entity->setTenantId($this->tenantId->toString());
        $entity->setBundleProductId($this->bundleProductId->toString());
        $entity->setProductId($this->productId->toString());
        $entity->setQuantity(3);
        $entity->setPriceAmount(2500);
        $entity->setPriceCurrency('EUR');
        $entity->setUpdatedAt($newUpdatedAt);

        self::assertSame('custom-id-123', $entity->getId());
        self::assertSame($this->tenantId->toString(), $entity->getTenantId());
        self::assertSame($this->bundleProductId->toString(), $entity->getBundleProductId());
        self::assertSame($this->productId->toString(), $entity->getProductId());
        self::assertSame(3, $entity->getQuantity());
        self::assertSame(2500, $entity->getPriceAmount());
        self::assertSame('EUR', $entity->getPriceCurrency());
        self::assertSame($newUpdatedAt->getTimestamp(), $entity->getUpdatedAt()->getTimestamp());
    }

    public function testFromPersistenceRoundtrip(): void
    {
        $bundleItem = BundleItem::fromPersistence(
            $this->productId,
            10,
            Money::fromScalars(500, 'EUR')
        );

        $entity = BundleItemEntity::fromDomainModel(
            $bundleItem,
            $this->bundleProductId,
            $this->tenantId,
            $this->entityId
        );

        $restored = $entity->toDomainModel();
        self::assertSame(10, $restored->quantity());
        self::assertSame(500, $restored->price()->getAmount());
        self::assertSame($this->productId->toString(), $restored->productId()->toString());
    }

    public function testTotalPriceCalculationOnRestoredDomain(): void
    {
        $bundleItem = BundleItem::create(
            $this->productId,
            3,
            Money::fromScalars(1000, 'EUR')
        );

        $entity = BundleItemEntity::fromDomainModel(
            $bundleItem,
            $this->bundleProductId,
            $this->tenantId,
            $this->entityId
        );

        $restored = $entity->toDomainModel();
        $total = $restored->totalPrice();

        self::assertSame(3000, $total->getAmount());
    }
}
