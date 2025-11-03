<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Application\DTO\InventoryStatsDto;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final class InventoryStatsProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId) {
            return $this->getEmptyStats();
        }

        // Get warehouse stats
        $warehouseStats = $this->getWarehouseStats($tenantId);

        // Get low stock alerts
        $lowStockAlerts = $this->getLowStockAlerts($tenantId);

        // Get top products by stock
        $topProducts = $this->getTopProducts($tenantId);

        // Get stock levels distribution
        $stockLevels = $this->getStockLevels($tenantId);

        // Calculate summary
        $summary = $this->calculateSummary($tenantId, $warehouseStats);

        return new InventoryStatsDto(
            summary: $summary,
            warehouses: $warehouseStats,
            stockLevels: $stockLevels,
            lowStockAlerts: $lowStockAlerts,
            topProducts: $topProducts,
            stockMovement: [
                'turnoverRate' => 0, // TODO: Calculate actual turnover rate
                'itemsCount' => $summary['totalProducts']
            ],
            generatedAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );
    }

    private function getEmptyStats(): InventoryStatsDto
    {
        return new InventoryStatsDto(
            summary: [
                'totalOnHand' => 0,
                'totalReserved' => 0,
                'totalAllocated' => 0,
                'totalAvailable' => 0,
                'lowStockCount' => 0,
                'totalProducts' => 0,
                'totalWarehouses' => 0
            ],
            warehouses: [],
            stockLevels: [],
            lowStockAlerts: [],
            topProducts: [],
            stockMovement: [
                'turnoverRate' => 0,
                'itemsCount' => 0
            ],
            generatedAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );
    }

    private function calculateSummary(string $tenantId, array $warehouseStats): array
    {
        $connection = $this->entityManager->getConnection();

        // Get totals from stock_items
        $totals = $connection->fetchAssociative(
            'SELECT
                COALESCE(SUM(on_hand), 0) as total_on_hand,
                COALESCE(SUM(reserved), 0) as total_reserved,
                COALESCE(SUM(allocated), 0) as total_allocated,
                COUNT(DISTINCT product_id) as total_products
             FROM stock_items
             WHERE tenant_id = :tenantId',
            ['tenantId' => $tenantId]
        );

        $lowStockCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM stock_items
             WHERE tenant_id = :tenantId AND on_hand < low_stock_threshold',
            ['tenantId' => $tenantId]
        );

        $totalWarehouses = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM warehouses
             WHERE tenant_id = :tenantId AND is_active = true',
            ['tenantId' => $tenantId]
        );

        return [
            'totalOnHand' => (int) $totals['total_on_hand'],
            'totalReserved' => (int) $totals['total_reserved'],
            'totalAllocated' => (int) $totals['total_allocated'],
            'totalAvailable' => (int) $totals['total_on_hand'] - (int) $totals['total_reserved'] - (int) $totals['total_allocated'],
            'lowStockCount' => $lowStockCount,
            'totalProducts' => (int) $totals['total_products'],
            'totalWarehouses' => $totalWarehouses
        ];
    }

    private function getWarehouseStats(string $tenantId): array
    {
        $connection = $this->entityManager->getConnection();

        $results = $connection->fetchAllAssociative(
            'SELECT
                w.name as warehouse_name,
                w.code as warehouse_code,
                COUNT(DISTINCT si.product_id) as product_count,
                COALESCE(SUM(si.on_hand), 0) as total_on_hand,
                COALESCE(SUM(si.reserved), 0) as total_reserved,
                COALESCE(SUM(si.on_hand - si.reserved - si.allocated), 0) as total_available
             FROM warehouses w
             LEFT JOIN stock_items si ON w.id = si.warehouse_id AND si.tenant_id = :tenantId
             WHERE w.tenant_id = :tenantId AND w.is_active = true
             GROUP BY w.id, w.name, w.code
             ORDER BY total_on_hand DESC',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'warehouse_name' => $row['warehouse_name'],
                'warehouse_code' => $row['warehouse_code'],
                'product_count' => (int) $row['product_count'],
                'total_on_hand' => (int) $row['total_on_hand'],
                'total_reserved' => (int) $row['total_reserved'],
                'total_available' => (int) $row['total_available']
            ];
        }, $results);
    }

    private function getLowStockAlerts(string $tenantId): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        $results = $connection->fetchAllAssociative(
            'SELECT
                p.id as product_id,
                p.name as product_name,
                p.sku as product_sku,
                w.name as warehouse_name,
                si.on_hand,
                si.reserved,
                si.allocated,
                (si.on_hand - si.reserved - si.allocated) as available,
                si.low_stock_threshold
             FROM stock_items si
             JOIN catalog_products p ON si.product_id = p.id
             JOIN warehouses w ON si.warehouse_id = w.id
             WHERE si.tenant_id = :tenantId
             AND si.on_hand < si.low_stock_threshold
             ORDER BY (si.on_hand - si.low_stock_threshold) ASC
             LIMIT 10',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'product_sku' => $row['product_sku'] ?? 'N/A',
                'warehouse_name' => $row['warehouse_name'],
                'on_hand' => (int) $row['on_hand'],
                'reserved' => (int) $row['reserved'],
                'allocated' => (int) $row['allocated'],
                'available' => (int) $row['available'],
                'low_stock_threshold' => (int) $row['low_stock_threshold']
            ];
        }, $results);
    }

    private function getTopProducts(string $tenantId): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        $results = $connection->fetchAllAssociative(
            'SELECT
                p.id as product_id,
                p.name as product_name,
                p.sku as product_sku,
                COALESCE(SUM(si.on_hand), 0) as total_on_hand,
                COALESCE(SUM(si.reserved), 0) as total_reserved,
                COALESCE(SUM(si.on_hand - si.reserved - si.allocated), 0) as total_available,
                COUNT(DISTINCT si.warehouse_id) as warehouse_count
             FROM catalog_products p
             LEFT JOIN stock_items si ON p.id = si.product_id AND si.tenant_id = :tenantId
             WHERE p.tenant_id = :tenantId AND p.active = true
             GROUP BY p.id, p.name, p.sku
             HAVING SUM(si.on_hand) > 0
             ORDER BY total_on_hand DESC
             LIMIT 10',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'product_sku' => $row['product_sku'] ?? 'N/A',
                'total_on_hand' => (int) $row['total_on_hand'],
                'total_reserved' => (int) $row['total_reserved'],
                'total_available' => (int) $row['total_available'],
                'warehouse_count' => (int) $row['warehouse_count']
            ];
        }, $results);
    }

    private function getStockLevels(string $tenantId): array
    {
        $connection = $this->entityManager->getConnection();

        // Categorize stock into levels: critical, low, medium, high
        $result = $connection->fetchAllAssociative(
            "SELECT
                stock_level,
                COUNT(*) as count
             FROM (
                SELECT
                    CASE
                        WHEN on_hand = 0 THEN 'Out of Stock'
                        WHEN on_hand < low_stock_threshold THEN 'Critical'
                        WHEN on_hand < (low_stock_threshold * 2) THEN 'Low'
                        WHEN on_hand < (low_stock_threshold * 5) THEN 'Medium'
                        ELSE 'High'
                    END as stock_level
                FROM stock_items
                WHERE tenant_id = :tenantId
             ) AS levels
             GROUP BY stock_level
             ORDER BY
                CASE stock_level
                    WHEN 'Out of Stock' THEN 1
                    WHEN 'Critical' THEN 2
                    WHEN 'Low' THEN 3
                    WHEN 'Medium' THEN 4
                    WHEN 'High' THEN 5
                END",
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'stock_level' => $row['stock_level'],
                'count' => (int) $row['count']
            ];
        }, $result);
    }
}
