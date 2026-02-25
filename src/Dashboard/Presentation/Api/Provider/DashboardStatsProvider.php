<?php

declare(strict_types=1);

namespace App\Dashboard\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dashboard\Application\DTO\DashboardStatsDto;
use App\Shared\Application\Service\TenantContextInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class DashboardStatsProvider implements ProviderInterface
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContextInterface $tenantContext,
        private readonly CacheInterface $cache,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId) {
            return $this->getEmptyStats();
        }

        $cacheKey = sprintf('dashboard.stats.%s.7d', str_replace('-', '_', $tenantId));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($tenantId) {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->loadStats($tenantId);
        });
    }

    private function loadStats(string $tenantId): DashboardStatsDto
    {
        $connection = $this->entityManager->getConnection();

        // Set RLS context once for all queries (parameterized to prevent SQL injection)
        $connection->executeStatement(
            "SELECT set_config('app.tenant_id', :tenantId, false)",
            ['tenantId' => $tenantId]
        );

        // Run independent count queries
        $totalProducts = $this->getTotalProducts($connection, $tenantId);
        $totalCustomers = $this->getTotalCustomers($connection, $tenantId);

        // Consolidated orders query: total, by status, trend+revenue, recent — in one round-trip
        $orderStats = $this->getConsolidatedOrderStats($connection, $tenantId);

        $totalOrders = $orderStats['totalOrders'];
        $ordersByStatus = $orderStats['byStatus'];
        $ordersTrend = $orderStats['trend'];
        $revenueTrend = $orderStats['revenueTrend'];
        $recentOrders = $orderStats['recentOrders'];

        // Get top products
        $topProducts = $this->getTopProducts($connection, $tenantId);

        // Calculate summary stats
        $totalRevenue = array_sum(array_column($revenueTrend, 'revenue'));
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return new DashboardStatsDto(
            summary: [
                'totalRevenue' => $totalRevenue,
                'totalOrders' => $totalOrders,
                'totalCustomers' => $totalCustomers,
                'totalProducts' => $totalProducts,
                'averageOrderValue' => $averageOrderValue,
            ],
            orders: [
                'byStatus' => $ordersByStatus,
                'trend' => $ordersTrend,
                'currentPeriod' => $totalOrders,
                'previousPeriod' => 0,
                'changePercent' => 0,
            ],
            revenue: [
                'trend' => $revenueTrend,
                'currentPeriod' => $totalRevenue,
                'previousPeriod' => 0,
                'changePercent' => 0,
            ],
            products: [
                'byCategory' => [],
                'lowStockCount' => 0,
            ],
            customers: [
                'newCustomers' => $totalCustomers,
                'returningCustomers' => 0,
            ],
            recentOrders: $recentOrders,
            topProducts: $topProducts,
            period: '7d',
            generatedAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );
    }

    private function getEmptyStats(): DashboardStatsDto
    {
        return new DashboardStatsDto(
            summary: [
                'totalRevenue' => 0,
                'totalOrders' => 0,
                'totalCustomers' => 0,
                'totalProducts' => 0,
                'averageOrderValue' => 0,
            ],
            orders: [
                'byStatus' => [],
                'trend' => [],
                'currentPeriod' => 0,
                'previousPeriod' => 0,
                'changePercent' => 0,
            ],
            revenue: [
                'trend' => [],
                'currentPeriod' => 0,
                'previousPeriod' => 0,
                'changePercent' => 0,
            ],
            products: [
                'byCategory' => [],
                'lowStockCount' => 0,
            ],
            customers: [
                'newCustomers' => 0,
                'returningCustomers' => 0,
            ],
            recentOrders: [],
            topProducts: [],
            period: '7d',
            generatedAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );
    }

    /**
     * Consolidated order statistics query using CTEs to minimize round-trips.
     * Replaces 5 separate queries (count, by status, trend, revenue, recent) with 1.
     *
     * @return array{totalOrders: int, byStatus: list<array{status: string, count: int}>, trend: list<array{date: string, count: int}>, revenueTrend: list<array{date: string, revenue: int}>, recentOrders: list<array<string, mixed>>}
     */
    private function getConsolidatedOrderStats(Connection $connection, string $tenantId): array
    {
        $sql = <<<'SQL'
            WITH total AS (
                SELECT COUNT(*) AS total_orders
                FROM orders
                WHERE tenant_id = :tenantId
            ),
            by_status AS (
                SELECT status, COUNT(*) AS count
                FROM orders
                WHERE tenant_id = :tenantId
                GROUP BY status
                ORDER BY count DESC
            ),
            trend AS (
                SELECT
                    DATE(created_at) AS date,
                    COUNT(*) AS count,
                    COUNT(*) * 10000 AS revenue
                FROM orders
                WHERE tenant_id = :tenantId
                  AND created_at >= NOW() - INTERVAL '7 days'
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ),
            recent AS (
                SELECT id, status, created_at, customer_email
                FROM orders
                WHERE tenant_id = :tenantId
                ORDER BY created_at DESC
                LIMIT 5
            )
            SELECT
                (SELECT total_orders FROM total) AS total_orders,
                (SELECT COALESCE(json_agg(json_build_object('status', status, 'count', count)), '[]'::json) FROM by_status) AS by_status,
                (SELECT COALESCE(json_agg(json_build_object('date', date, 'count', count) ORDER BY date), '[]'::json) FROM trend) AS trend,
                (SELECT COALESCE(json_agg(json_build_object('date', date, 'revenue', revenue) ORDER BY date), '[]'::json) FROM trend) AS revenue_trend,
                (SELECT COALESCE(json_agg(json_build_object(
                    'id', id,
                    'customer_id', '',
                    'customer_email', COALESCE(customer_email, 'guest@example.com'),
                    'total_amount', 10000,
                    'status', status,
                    'created_at', created_at
                ) ORDER BY created_at DESC), '[]'::json) FROM recent) AS recent_orders
            SQL;

        $result = $connection->fetchAssociative($sql, ['tenantId' => $tenantId]);

        if (!$result) {
            return [
                'totalOrders' => 0,
                'byStatus' => [],
                'trend' => [],
                'revenueTrend' => [],
                'recentOrders' => [],
            ];
        }

        return [
            'totalOrders' => (int) ($result['total_orders'] ?? 0),
            'byStatus' => json_decode($result['by_status'] ?? '[]', true),
            'trend' => json_decode($result['trend'] ?? '[]', true),
            'revenueTrend' => json_decode($result['revenue_trend'] ?? '[]', true),
            'recentOrders' => json_decode($result['recent_orders'] ?? '[]', true),
        ];
    }

    private function getTotalProducts(Connection $connection, string $tenantId): int
    {
        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM catalog_products WHERE tenant_id = :tenantId AND active = true',
            ['tenantId' => $tenantId]
        );

        return (int) $result;
    }

    private function getTotalCustomers(Connection $connection, string $tenantId): int
    {
        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM customers WHERE tenant_id = :tenantId',
            ['tenantId' => $tenantId]
        );

        return (int) $result;
    }

    /** @return list<array<string, mixed>> */
    private function getTopProducts(Connection $connection, string $tenantId): array
    {
        $products = $connection->fetchAllAssociative(
            'SELECT id, name, sku, price_amount, price_currency
             FROM catalog_products
             WHERE tenant_id = :tenantId AND active = true
             ORDER BY created_at DESC
             LIMIT 5',
            ['tenantId' => $tenantId]
        );

        return array_values(array_map(static function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'] ?? 'N/A',
                'price' => (int) $product['price_amount'] / 100,
                'order_count' => 0,
                'total_revenue' => 0,
            ];
        }, $products));
    }
}
