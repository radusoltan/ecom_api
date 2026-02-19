<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Service;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Order\Application\Service\CheckoutValidationService;
use App\Order\Domain\Exception\CheckoutValidationException;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckoutValidationService::class)]
final class CheckoutValidationServiceTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private StockItemRepositoryInterface $stockItemRepository;
    private CheckoutValidationService $service;
    private TenantId $tenantId;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PRODUCT_ID_1 = '550e8400-e29b-41d4-a716-446655440001';
    private const PRODUCT_ID_2 = '550e8400-e29b-41d4-a716-446655440002';

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);
        $this->tenantId = TenantId::fromString(self::TENANT_ID);

        $this->service = new CheckoutValidationService(
            $this->productRepository,
            $this->stockItemRepository,
        );
    }

    #[Test]
    public function itPassesWhenPricesAndStockAreValid(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Widget', 2, 1000, 'EUR')];

        $this->stubProductPrices([self::PRODUCT_ID_1 => ['amount' => 1000, 'currency' => 'EUR']]);
        $this->stubStockLevels([self::PRODUCT_ID_1 => 10]);

        $this->service->validate($lines, $this->tenantId);
        $this->assertTrue(true); // No exception means validation passed
    }

    #[Test]
    public function itDetectsPriceIncrease(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Widget', 1, 1000, 'EUR')];

        $this->stubProductPrices([self::PRODUCT_ID_1 => ['amount' => 1499, 'currency' => 'EUR']]);
        $this->stubStockLevels([self::PRODUCT_ID_1 => 10]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertTrue($e->hasPriceChanges());
            $this->assertFalse($e->hasStockIssues());
            $this->assertCount(1, $e->priceChanges());
            $this->assertSame(1000, $e->priceChanges()[0]['oldPrice']);
            $this->assertSame(1499, $e->priceChanges()[0]['newPrice']);
            $this->assertSame('EUR', $e->priceChanges()[0]['currency']);
        }
    }

    #[Test]
    public function itDetectsPriceDecrease(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Widget', 1, 2000, 'EUR')];

        $this->stubProductPrices([self::PRODUCT_ID_1 => ['amount' => 1500, 'currency' => 'EUR']]);
        $this->stubStockLevels([self::PRODUCT_ID_1 => 10]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertTrue($e->hasPriceChanges());
            $this->assertSame(2000, $e->priceChanges()[0]['oldPrice']);
            $this->assertSame(1500, $e->priceChanges()[0]['newPrice']);
        }
    }

    #[Test]
    public function itDetectsOutOfStock(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Widget', 5, 1000, 'EUR')];

        $this->stubProductPrices([self::PRODUCT_ID_1 => ['amount' => 1000, 'currency' => 'EUR']]);
        $this->stubStockLevels([self::PRODUCT_ID_1 => 0]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertFalse($e->hasPriceChanges());
            $this->assertTrue($e->hasStockIssues());
            $this->assertCount(1, $e->stockIssues());
            $this->assertSame(5, $e->stockIssues()[0]['requestedQuantity']);
            $this->assertSame(0, $e->stockIssues()[0]['availableQuantity']);
        }
    }

    #[Test]
    public function itDetectsPartialStock(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Widget', 5, 1000, 'EUR')];

        $this->stubProductPrices([self::PRODUCT_ID_1 => ['amount' => 1000, 'currency' => 'EUR']]);
        $this->stubStockLevels([self::PRODUCT_ID_1 => 3]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertTrue($e->hasStockIssues());
            $this->assertSame(5, $e->stockIssues()[0]['requestedQuantity']);
            $this->assertSame(3, $e->stockIssues()[0]['availableQuantity']);
        }
    }

    #[Test]
    public function itCollectsMultipleMixedIssues(): void
    {
        $lines = [
            $this->createLine(self::PRODUCT_ID_1, 'Widget', 2, 1000, 'EUR'),
            $this->createLine(self::PRODUCT_ID_2, 'Gadget', 5, 2000, 'EUR'),
        ];

        // Product 1: price changed (1000 → 1500), Product 2: price unchanged
        $this->stubProductPrices([
            self::PRODUCT_ID_1 => ['amount' => 1500, 'currency' => 'EUR'],
            self::PRODUCT_ID_2 => ['amount' => 2000, 'currency' => 'EUR'],
        ]);

        // Product 1: stock OK (10 available), Product 2: insufficient stock (2 available, 5 requested)
        $this->stubStockLevels([
            self::PRODUCT_ID_1 => 10,
            self::PRODUCT_ID_2 => 2,
        ]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertTrue($e->hasPriceChanges());
            $this->assertTrue($e->hasStockIssues());
            $this->assertCount(1, $e->priceChanges());
            $this->assertCount(1, $e->stockIssues());
            $this->assertSame(self::PRODUCT_ID_1, $e->priceChanges()[0]['productId']);
            $this->assertSame(self::PRODUCT_ID_2, $e->stockIssues()[0]['productId']);

            $array = $e->toArray();
            $this->assertSame('checkout_validation_failed', $array['error']);
            $this->assertArrayHasKey('priceChanges', $array);
            $this->assertArrayHasKey('stockIssues', $array);
        }
    }

    #[Test]
    public function itTreatsDeletedProductAsPriceChangeToZero(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Deleted Widget', 1, 1000, 'EUR')];

        // Product no longer exists
        $this->stubProductPrices([self::PRODUCT_ID_1 => null]);
        $this->stubStockLevels([self::PRODUCT_ID_1 => 0]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertTrue($e->hasPriceChanges());
            $this->assertSame(0, $e->priceChanges()[0]['newPrice']);
            $this->assertSame(1000, $e->priceChanges()[0]['oldPrice']);
        }
    }

    #[Test]
    public function itTreatsNoStockItemsAsZeroAvailable(): void
    {
        $lines = [$this->createLine(self::PRODUCT_ID_1, 'Widget', 1, 1000, 'EUR')];

        $this->stubProductPrices([self::PRODUCT_ID_1 => ['amount' => 1000, 'currency' => 'EUR']]);
        // No stock items exist at all
        $this->stubStockLevels([self::PRODUCT_ID_1 => 0]);

        try {
            $this->service->validate($lines, $this->tenantId);
            $this->fail('Expected CheckoutValidationException');
        } catch (CheckoutValidationException $e) {
            $this->assertTrue($e->hasStockIssues());
            $this->assertSame(0, $e->stockIssues()[0]['availableQuantity']);
        }
    }

    /**
     * @return array{productId: string, productName: string, quantity: int, unitPriceAmount: int, unitPriceCurrency: string}
     */
    private function createLine(string $productId, string $name, int $quantity, int $priceAmount, string $currency): array
    {
        return [
            'productId' => $productId,
            'productName' => $name,
            'quantity' => $quantity,
            'unitPriceAmount' => $priceAmount,
            'unitPriceCurrency' => $currency,
        ];
    }

    /**
     * @param array<string, array{amount: int, currency: string}|null> $products productId => price data or null for deleted
     */
    private function stubProductPrices(array $products): void
    {
        $this->productRepository
            ->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($products): ?Product {
                $priceData = $products[$id->toString()] ?? null;

                if (null === $priceData) {
                    return null;
                }

                $product = $this->createMock(Product::class);
                $product->method('price')->willReturn(
                    Money::fromScalars($priceData['amount'], $priceData['currency']),
                );

                return $product;
            });
    }

    /**
     * @param array<string, int> $stockLevels productId => available quantity
     */
    private function stubStockLevels(array $stockLevels): void
    {
        $tenantId = $this->tenantId;

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturnCallback(function (ProductId $productId, TenantId $tid) use ($stockLevels, $tenantId): array {
                $available = $stockLevels[$productId->toString()] ?? 0;

                if (0 === $available) {
                    return [];
                }

                return [
                    StockItem::create(
                        StockItemId::generate(),
                        $tenantId,
                        $productId,
                        WarehouseId::generate(),
                        Quantity::fromInt($available),
                    ),
                ];
            });
    }
}
