<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Inventory\Application\DTO\InventoryStatsDto;
use App\Inventory\Presentation\Api\Provider\InventoryStatsProvider;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(InventoryStatsProvider::class)]
final class InventoryStatsProviderTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private TenantContext $tenantContext;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->operation = $this->createMock(Operation::class);

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);
    }

    // ---------------------------------------------------------------
    // No tenant ID → empty stats
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturnsEmptyStatsWhenNoTenantId(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        $this->entityManager->expects(self::never())->method('getConnection');

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        self::assertInstanceOf(InventoryStatsDto::class, $result);
        self::assertSame(0, $result->summary['totalOnHand']);
        self::assertSame(0, $result->summary['totalReserved']);
        self::assertSame(0, $result->summary['totalAllocated']);
        self::assertSame(0, $result->summary['totalAvailable']);
        self::assertSame(0, $result->summary['lowStockCount']);
        self::assertSame(0, $result->summary['totalProducts']);
        self::assertSame(0, $result->summary['totalWarehouses']);
        self::assertSame([], $result->warehouses);
        self::assertSame([], $result->stockLevels);
        self::assertSame([], $result->lowStockAlerts);
        self::assertSame([], $result->topProducts);
        self::assertSame(0, $result->stockMovement['turnoverRate']);
        self::assertSame(0, $result->stockMovement['itemsCount']);
    }

    // ---------------------------------------------------------------
    // With tenant ID → full stats built from DB queries
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturnsFullStatsWhenTenantIdPresent(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        // RLS set
        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                "SELECT set_config('app.tenant_id', :tenantId, false)",
                ['tenantId' => $tenantId]
            );

        // Warehouse stats query
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'FROM warehouses w')) {
                    return [
                        [
                            'warehouse_name' => 'Main Warehouse',
                            'warehouse_code' => 'MW-01',
                            'product_count' => '5',
                            'total_on_hand' => '100',
                            'total_reserved' => '10',
                            'total_available' => '90',
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM stock_items si') && str_contains($sql, 'catalog_products p')) {
                    if (str_contains($sql, 'low_stock_threshold')) {
                        return [
                            [
                                'product_id' => 'prod-uuid-1',
                                'product_name' => 'Widget A',
                                'product_sku' => 'WA-001',
                                'warehouse_name' => 'Main Warehouse',
                                'on_hand' => '3',
                                'reserved' => '1',
                                'allocated' => '0',
                                'available' => '2',
                                'low_stock_threshold' => '10',
                            ],
                        ];
                    }

                    return [
                        [
                            'product_id' => 'prod-uuid-1',
                            'product_name' => 'Widget A',
                            'product_sku' => 'WA-001',
                            'total_on_hand' => '50',
                            'total_reserved' => '5',
                            'total_available' => '45',
                            'warehouse_count' => '2',
                        ],
                    ];
                }

                // Stock levels subquery
                return [
                    ['stock_level' => 'High', 'count' => '8'],
                    ['stock_level' => 'Low',  'count' => '2'],
                ];
            });

        // Summary sub-queries (fetchAssociative + fetchOne × 2)
        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'total_on_hand' => '100',
                'total_reserved' => '10',
                'total_allocated' => '5',
                'total_products' => '12',
            ]);

        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('3', '2');

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        self::assertInstanceOf(InventoryStatsDto::class, $result);
        self::assertSame(100, $result->summary['totalOnHand']);
        self::assertSame(10, $result->summary['totalReserved']);
        self::assertSame(5, $result->summary['totalAllocated']);
        self::assertSame(85, $result->summary['totalAvailable']);
        self::assertSame(3, $result->summary['lowStockCount']);
        self::assertSame(12, $result->summary['totalProducts']);
        self::assertSame(2, $result->summary['totalWarehouses']);

        // Warehouses
        self::assertCount(1, $result->warehouses);
        self::assertSame('Main Warehouse', $result->warehouses[0]['warehouse_name']);
        self::assertSame(5, $result->warehouses[0]['product_count']);
        self::assertSame(100, $result->warehouses[0]['total_on_hand']);

        // Stock movement carries totalProducts
        self::assertSame(0, $result->stockMovement['turnoverRate']);
        self::assertSame(12, $result->stockMovement['itemsCount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itMapsLowStockAlertFieldsCorrectly(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $this->connection->method('executeStatement')->willReturn(0);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'low_stock_threshold')) {
                    return [
                        [
                            'product_id' => 'p1',
                            'product_name' => 'Product One',
                            'product_sku' => null,          // null SKU → 'N/A'
                            'warehouse_name' => 'WH-1',
                            'on_hand' => '2',
                            'reserved' => '1',
                            'allocated' => '0',
                            'available' => '1',
                            'low_stock_threshold' => '5',
                        ],
                    ];
                }

                return [];
            });

        $this->connection->method('fetchAssociative')->willReturn([
            'total_on_hand' => '0',
            'total_reserved' => '0',
            'total_allocated' => '0',
            'total_products' => '0',
        ]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('0', '0');

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        $alert = $result->lowStockAlerts[0];
        self::assertSame('p1', $alert['product_id']);
        self::assertSame('N/A', $alert['product_sku']);
        self::assertSame(2, $alert['on_hand']);
        self::assertSame(5, $alert['low_stock_threshold']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itMapsTopProductsFieldsCorrectly(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $this->connection->method('executeStatement')->willReturn(0);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'catalog_products p') && str_contains($sql, 'p.active')) {
                    return [
                        [
                            'product_id' => 'p2',
                            'product_name' => 'Product Two',
                            'product_sku' => null,      // null → 'N/A'
                            'total_on_hand' => '50',
                            'total_reserved' => '5',
                            'total_available' => '45',
                            'warehouse_count' => '3',
                        ],
                    ];
                }

                return [];
            });

        $this->connection->method('fetchAssociative')->willReturn([
            'total_on_hand' => '0',
            'total_reserved' => '0',
            'total_allocated' => '0',
            'total_products' => '0',
        ]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('0', '0');

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        $top = $result->topProducts[0];
        self::assertSame('p2', $top['product_id']);
        self::assertSame('N/A', $top['product_sku']);
        self::assertSame(3, $top['warehouse_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itMapsStockLevelsCorrectly(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $this->connection->method('executeStatement')->willReturn(0);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'stock_level')) {
                    return [
                        ['stock_level' => 'Out of Stock', 'count' => '1'],
                        ['stock_level' => 'Critical',     'count' => '2'],
                        ['stock_level' => 'Medium',       'count' => '7'],
                    ];
                }

                return [];
            });

        $this->connection->method('fetchAssociative')->willReturn([
            'total_on_hand' => '0',
            'total_reserved' => '0',
            'total_allocated' => '0',
            'total_products' => '0',
        ]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('0', '0');

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        self::assertCount(3, $result->stockLevels);
        self::assertSame('Out of Stock', $result->stockLevels[0]['stock_level']);
        self::assertSame(1, $result->stockLevels[0]['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itCalculatesTotalAvailableCorrectly(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $this->connection->method('executeStatement')->willReturn(0);
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        // onHand=200, reserved=30, allocated=20 → available=150
        $this->connection->method('fetchAssociative')->willReturn([
            'total_on_hand' => '200',
            'total_reserved' => '30',
            'total_allocated' => '20',
            'total_products' => '5',
        ]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('0', '1');

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        self::assertSame(150, $result->summary['totalAvailable']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itIncludesGeneratedAtTimestamp(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        $provider = new InventoryStatsProvider($this->entityManager, $this->tenantContext);
        $result = $provider->provide($this->operation);

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result->generatedAt);
    }
}
