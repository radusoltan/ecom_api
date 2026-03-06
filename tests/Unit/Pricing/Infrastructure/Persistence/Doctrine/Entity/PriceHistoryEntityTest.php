<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Domain\ValueObject\PriceChangeSource;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceHistoryEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PriceHistoryEntityTest extends TestCase
{
    public function testFromPriceChangeRoundtrip(): void
    {
        $productId = ProductId::generate();
        $oldPrice = Money::of('19.99', 'EUR');
        $newPrice = Money::of('24.99', 'EUR');
        $tenantId = TenantId::generate();
        $userId = UserId::generate();

        $priceChange = PriceChange::create(
            $productId, $oldPrice, $newPrice, 'Price increase', PriceChangeSource::MANUAL,
        );

        $entity = PriceHistoryEntity::fromPriceChange($priceChange, $tenantId, $userId);

        self::assertNotEmpty($entity->id());
        self::assertSame($tenantId->toString(), $entity->tenantId());
        self::assertSame($productId->toString(), $entity->productId());
        self::assertNotNull($entity->oldPriceAmount());
        self::assertSame('EUR', $entity->oldPriceCurrency());
        self::assertNotNull($entity->newPriceAmount());
        self::assertSame('EUR', $entity->newPriceCurrency());
        self::assertSame('Price increase', $entity->changeReason());
        self::assertSame($userId->toString(), $entity->changedBy());
        self::assertSame('manual', $entity->source());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->changedAt());

        // Roundtrip
        $restored = $entity->toPriceChange();
        self::assertSame($productId->toString(), $restored->productId()->toString());

        // toDomainModel alias
        $restored2 = $entity->toDomainModel();
        self::assertSame($productId->toString(), $restored2->productId()->toString());
    }

    public function testFromDomainModelAlias(): void
    {
        $priceChange = PriceChange::create(
            ProductId::generate(), Money::of('10.00', 'USD'), Money::of('15.00', 'USD'),
            'Promotion', PriceChangeSource::PROMOTION,
        );

        $entity = PriceHistoryEntity::fromDomainModel($priceChange, TenantId::generate());
        self::assertSame('promotion', $entity->source());
        self::assertNull($entity->changedBy());
    }

    public function testGetOldPriceAndNewPrice(): void
    {
        $priceChange = PriceChange::create(
            ProductId::generate(), Money::of('100.00', 'GBP'), Money::of('80.00', 'GBP'),
            null, PriceChangeSource::FLASH_SALE,
        );

        $entity = PriceHistoryEntity::fromPriceChange($priceChange, TenantId::generate());

        $oldPrice = $entity->getOldPrice();
        self::assertIsArray($oldPrice);
        self::assertSame('GBP', $oldPrice['currency']);

        $newPrice = $entity->getNewPrice();
        self::assertIsArray($newPrice);
        self::assertSame('GBP', $newPrice['currency']);
    }

    public function testNullPrices(): void
    {
        $priceChange = PriceChange::create(
            ProductId::generate(), null, Money::of('50.00', 'EUR'),
            'New product', PriceChangeSource::MANUAL,
        );

        $entity = PriceHistoryEntity::fromPriceChange($priceChange, TenantId::generate());

        self::assertNull($entity->getOldPrice());
        self::assertNull($entity->oldPriceAmount());
        self::assertNull($entity->oldPriceCurrency());
        self::assertNotNull($entity->getNewPrice());
    }

    public function testToArray(): void
    {
        $priceChange = PriceChange::create(
            ProductId::generate(), Money::of('10.00', 'EUR'), Money::of('20.00', 'EUR'),
            'Test', PriceChangeSource::IMPORT,
        );

        $entity = PriceHistoryEntity::fromPriceChange($priceChange, TenantId::generate());
        $array = $entity->toArray();

        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('tenant_id', $array);
        self::assertArrayHasKey('product_id', $array);
        self::assertArrayHasKey('old_price', $array);
        self::assertArrayHasKey('new_price', $array);
        self::assertArrayHasKey('change_reason', $array);
        self::assertArrayHasKey('changed_at', $array);
        self::assertArrayHasKey('source', $array);
        self::assertSame('import', $array['source']);
    }
}
