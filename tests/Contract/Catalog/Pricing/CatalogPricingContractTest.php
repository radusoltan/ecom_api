<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog\Pricing;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the Catalog <-> Pricing boundary.
 *
 * Verifies that Pricing events referencing Catalog entities (ProductId)
 * maintain consistent types, and that Catalog events consumed by
 * Pricing preserve their structure.
 *
 * Producer: Catalog (ProductCreated), Pricing (FlashSaleActivated)
 * Consumer: Pricing (PriceHistorySubscriber), Catalog (search indexing)
 */
#[CoversNothing]
#[Group('contract')]
final class CatalogPricingContractTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // -----------------------------------------------------------------------
    // ProductCreated — produced by Catalog
    // -----------------------------------------------------------------------

    #[Test]
    public function productCreatedHasRequiredFields(): void
    {
        $event = new ProductCreated(
            productId: ProductId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            sku: SKU::fromString('ABC-123456'),
            name: 'Test Product',
        );

        self::assertInstanceOf(ProductId::class, $event->productId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertInstanceOf(SKU::class, $event->sku);
        self::assertIsString($event->name);
        self::assertNotEmpty($event->name);
    }

    #[Test]
    public function productIdIsConsistentAcrossCatalogAndPricing(): void
    {
        $productId = ProductId::generate();
        $serialized = $productId->toString();

        // Pricing context must be able to deserialize Catalog's ProductId
        $deserialized = ProductId::fromString($serialized);
        self::assertSame($serialized, $deserialized->toString());
    }

    #[Test]
    public function skuValueObjectMatchesExpectedPattern(): void
    {
        $sku = SKU::fromString('ABC-123456');

        self::assertMatchesRegularExpression('/^[A-Z]{3}-[0-9]{6}$/', $sku->value());
    }

    // -----------------------------------------------------------------------
    // FlashSaleActivated — produced by Pricing, references Catalog ProductIds
    // -----------------------------------------------------------------------

    #[Test]
    public function flashSaleActivatedImplementsDomainEvent(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: FlashSaleId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: 'Summer Sale',
            productIds: [ProductId::generate(), ProductId::generate()],
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(DomainEvent::class, $event);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    #[Test]
    public function flashSaleActivatedHasRequiredFields(): void
    {
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();

        $event = new FlashSaleActivated(
            flashSaleId: FlashSaleId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: 'Black Friday',
            productIds: [$productId1, $productId2],
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(FlashSaleId::class, $event->flashSaleId());
        self::assertInstanceOf(TenantId::class, $event->tenantId());
        self::assertIsString($event->name());
        self::assertNotEmpty($event->name());
        self::assertCount(2, $event->productIds());
    }

    #[Test]
    public function flashSaleProductIdsAreCatalogProductIdInstances(): void
    {
        $productId = ProductId::generate();

        $event = new FlashSaleActivated(
            flashSaleId: FlashSaleId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: 'Flash Deal',
            productIds: [$productId],
            occurredAt: new \DateTimeImmutable(),
        );

        // Contract: Pricing uses Catalog's ProductId type
        foreach ($event->productIds() as $id) {
            self::assertInstanceOf(ProductId::class, $id);
        }
    }

    #[Test]
    public function flashSaleCanHaveEmptyProductList(): void
    {
        $event = new FlashSaleActivated(
            flashSaleId: FlashSaleId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: 'Category-wide sale',
            productIds: [],
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertEmpty($event->productIds());
    }

    // -----------------------------------------------------------------------
    // Cross-boundary serialization contract
    // -----------------------------------------------------------------------

    #[Test]
    public function productIdRoundTripsAcrossBoundary(): void
    {
        $original = ProductId::generate();
        $asString = $original->toString();

        // Simulate Pricing deserializing a ProductId from Catalog
        $fromPricing = ProductId::fromString($asString);
        self::assertSame($original->toString(), $fromPricing->toString());
    }

    #[Test]
    public function tenantIdIsConsistentAcrossCatalogAndPricing(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        self::assertSame(self::TENANT_ID, $tenantId->toString());
        self::assertSame(
            $tenantId->toString(),
            TenantId::fromString($tenantId->toString())->toString()
        );
    }
}
