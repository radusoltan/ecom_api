<?php

declare(strict_types=1);

namespace App\Dashboard\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dashboard\Application\DTO\DashboardStatsDto;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final class DashboardStatsProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId) {
            return $this->getEmptyStats();
        }

        // Get total counts
        $totalProducts = $this->getTotalProducts($tenantId);
        $totalOrders = $this->getTotalOrders($tenantId);
        $totalCustomers = $this->getTotalCustomers($tenantId);

        // Get order stats grouped by status
        $ordersByStatus = $this->getOrdersByStatus($tenantId);
        $ordersTrend = $this->getOrdersTrend($tenantId);

        // Get revenue stats (mock data for now - would need order_items table for real calculation)
        $revenueTrend = $this->getRevenueTrend($tenantId);

        // Get recent orders (last 5)
        $recentOrders = $this->getRecentOrders($tenantId);

        // Get top products (by order count)
        $topProducts = $this->getTopProducts($tenantId);

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
                'previousPeriod' => 0,  // TODO: Calculate previous period
                'changePercent' => 0,     // TODO: Calculate change percent
            ],
            revenue: [
                'trend' => $revenueTrend,
                'currentPeriod' => $totalRevenue,
                'previousPeriod' => 0,   // TODO: Calculate previous period
                'changePercent' => 0,      // TODO: Calculate change percent
            ],
            products: [
                'byCategory' => [],      // TODO: Implement category breakdown
                'lowStockCount' => 0,     // TODO: Implement low stock count
            ],
            customers: [
                'newCustomers' => $totalCustomers,
                'returningCustomers' => 0,  // TODO: Implement returning customers
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

    private function getTotalProducts(string $tenantId): int
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM catalog_products WHERE tenant_id = :tenantId AND active = true',
            ['tenantId' => $tenantId]
        );

        return (int) $result;
    }

    private function getTotalOrders(string $tenantId): int
    {
        $result = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM orders WHERE tenant_id = :tenantId',
            ['tenantId' => $tenantId]
        );

        return (int) $result;
    }

    private function getTotalCustomers(string $tenantId): int
    {
        $result = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM customers WHERE tenant_id = :tenantId',
            ['tenantId' => $tenantId]
        );

        return (int) $result;
    }

    private function getTotalCategories(string $tenantId): int
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM catalog_categories WHERE tenant_id = :tenantId AND active = true',
            ['tenantId' => $tenantId]
        );

        return (int) $result;
    }

    private function getOrdersByStatus(string $tenantId): array
    {
        $result = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT status, COUNT(*) as count
             FROM orders
             WHERE tenant_id = :tenantId
             GROUP BY status
             ORDER BY count DESC',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'status' => $row['status'],
                'count' => (int) $row['count'],
            ];
        }, $result);
    }

    private function getOrdersTrend(string $tenantId): array
    {
        // Get orders trend for last 7 days
        $result = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT DATE(created_at) as date, COUNT(*) as count
             FROM orders
             WHERE tenant_id = :tenantId AND created_at >= NOW() - INTERVAL \'7 days\'
             GROUP BY DATE(created_at)
             ORDER BY date ASC',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'date' => $row['date'],
                'count' => (int) $row['count'],
            ];
        }, $result);
    }

    private function getRevenueTrend(string $tenantId): array
    {
        // Mock revenue trend data for now
        // TODO: Implement real revenue calculation when order_items table exists
        $result = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT DATE(created_at) as date, COUNT(*) * 10000 as revenue
             FROM orders
             WHERE tenant_id = :tenantId AND created_at >= NOW() - INTERVAL \'7 days\'
             GROUP BY DATE(created_at)
             ORDER BY date ASC',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($row) {
            return [
                'date' => $row['date'],
                'revenue' => (int) $row['revenue'],
            ];
        }, $result);
    }

    private function getRecentOrders(string $tenantId): array
    {
        $orders = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, status, created_at, customer_email
             FROM orders
             WHERE tenant_id = :tenantId
             ORDER BY created_at DESC
             LIMIT 5',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($order) {
            return [
                'id' => $order['id'],
                'customer_id' => '', // Not available in orders table
                'customer_email' => $order['customer_email'] ?? 'guest@example.com',
                'total_amount' => 10000, // Mock data - TODO: calculate from lines JSON
                'status' => $order['status'],
                'created_at' => $order['created_at'],
            ];
        }, $orders);
    }

    private function getTopProducts(string $tenantId): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("SET app.tenant_id = '{$tenantId}'");

        // Get most recent products for now
        // TODO: Calculate actual order count and revenue from order_items
        $products = $connection->fetchAllAssociative(
            'SELECT id, name, sku, price_amount, price_currency
             FROM catalog_products
             WHERE tenant_id = :tenantId AND active = true
             ORDER BY created_at DESC
             LIMIT 5',
            ['tenantId' => $tenantId]
        );

        return array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'] ?? 'N/A',
                'price' => (int) $product['price_amount'] / 100, // Convert cents to dollars
                'order_count' => 0,  // TODO: Calculate from order_items
                'total_revenue' => 0,  // TODO: Calculate from order_items
            ];
        }, $products);
    }
}
