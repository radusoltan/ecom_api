<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use App\Dashboard\Application\DTO\DashboardStatsDto;
use App\Dashboard\Presentation\Api\Provider\DashboardStatsProvider;
use App\Shared\Application\Service\TenantContextInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(DashboardStatsProvider::class)]
final class DashboardStatsProviderTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TenantContextInterface&MockObject $tenantContext;
    private CacheInterface&MockObject $cache;
    private Connection&MockObject $connection;
    private DashboardStatsProvider $provider;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->provider = new DashboardStatsProvider(
            $this->entityManager,
            $this->tenantContext,
            $this->cache,
        );
    }

    #[Test]
    public function itReturnsEmptyStatsWhenNoTenant(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        // Cache should never be called when no tenant
        $this->cache->expects($this->never())->method('get');

        $result = $this->provider->provide(new Get());

        $this->assertInstanceOf(DashboardStatsDto::class, $result);
        $this->assertSame(0, $result->summary['totalOrders']);
        $this->assertSame(0, $result->summary['totalProducts']);
        $this->assertSame(0, $result->summary['totalCustomers']);
        $this->assertSame([], $result->recentOrders);
        $this->assertSame([], $result->topProducts);
    }

    #[Test]
    public function itReturnsCachedStatsOnCacheHit(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $cachedDto = new DashboardStatsDto(
            summary: ['totalRevenue' => 50000, 'totalOrders' => 5, 'totalCustomers' => 3, 'totalProducts' => 10, 'averageOrderValue' => 10000],
            orders: ['byStatus' => [], 'trend' => [], 'currentPeriod' => 5, 'previousPeriod' => 0, 'changePercent' => 0],
            revenue: ['trend' => [], 'currentPeriod' => 50000, 'previousPeriod' => 0, 'changePercent' => 0],
            products: ['byCategory' => [], 'lowStockCount' => 0],
            customers: ['newCustomers' => 3, 'returningCustomers' => 0],
            recentOrders: [],
            topProducts: [],
            period: '7d',
            generatedAt: '2026-02-25 12:00:00'
        );

        $this->cache->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('dashboard.stats.'),
                $this->isType('callable')
            )
            ->willReturn($cachedDto);

        // DB should never be called on cache hit
        $this->connection->expects($this->never())->method('executeStatement');

        $result = $this->provider->provide(new Get());

        $this->assertSame($cachedDto, $result);
    }

    #[Test]
    public function itQueriesDatabaseOnCacheMiss(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        // Simulate cache miss: execute the callback
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(300);

                return $callback($item);
            });

        // Expect RLS context to be set via parameterized call
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                "SELECT set_config('app.tenant_id', :tenantId, false)",
                ['tenantId' => $tenantId]
            );

        // Consolidated order stats CTE query
        $orderResult = $this->createMock(Result::class);
        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'total_orders' => '3',
                'by_status' => json_encode([['status' => 'confirmed', 'count' => 2], ['status' => 'pending', 'count' => 1]]),
                'trend' => json_encode([['date' => '2026-02-25', 'count' => 3]]),
                'revenue_trend' => json_encode([['date' => '2026-02-25', 'revenue' => 30000]]),
                'recent_orders' => json_encode([['id' => 'ord-1', 'customer_id' => '', 'customer_email' => 'test@example.com', 'total_amount' => 10000, 'status' => 'confirmed', 'created_at' => '2026-02-25 10:00:00']]),
            ]);

        // COUNT queries: products, customers
        $this->connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('10', '5');

        // Top products
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 'prod-1', 'name' => 'Widget', 'sku' => 'WDG-001', 'price_amount' => '2500', 'price_currency' => 'USD'],
            ]);

        $result = $this->provider->provide(new Get());

        $this->assertInstanceOf(DashboardStatsDto::class, $result);
        $this->assertSame(3, $result->summary['totalOrders']);
        $this->assertSame(10, $result->summary['totalProducts']);
        $this->assertSame(5, $result->summary['totalCustomers']);
        $this->assertSame(30000, $result->summary['totalRevenue']);
        $this->assertSame('7d', $result->period);

        // Verify orders by status
        $this->assertCount(2, $result->orders['byStatus']);
        $this->assertSame('confirmed', $result->orders['byStatus'][0]['status']);

        // Verify recent orders
        $this->assertCount(1, $result->recentOrders);
        $this->assertSame('ord-1', $result->recentOrders[0]['id']);

        // Verify top products
        $this->assertCount(1, $result->topProducts);
        $this->assertSame('Widget', $result->topProducts[0]['name']);
    }

    #[Test]
    public function cacheKeyIncludesTenantId(): void
    {
        $tenantId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $this->cache->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo('dashboard.stats.aaaaaaaa_bbbb_4ccc_8ddd_eeeeeeeeeeee.7d'),
                $this->isType('callable')
            )
            ->willReturn($this->createEmptyDto());

        $this->provider->provide(new Get());
    }

    #[Test]
    public function itHandlesEmptyOrdersGracefully(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $this->tenantContext->method('getTenantId')->willReturn($tenantId);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                return $callback($item);
            });

        $this->connection->method('executeStatement');

        // Consolidated query returns null (no rows)
        $this->connection->method('fetchAssociative')->willReturn(false);

        // COUNT queries
        $this->connection->method('fetchOne')->willReturn('0');

        // Top products: empty
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->provider->provide(new Get());

        $this->assertInstanceOf(DashboardStatsDto::class, $result);
        $this->assertSame(0, $result->summary['totalOrders']);
        $this->assertSame(0, $result->summary['totalRevenue']);
        $this->assertSame(0, $result->summary['averageOrderValue']);
        $this->assertSame([], $result->orders['byStatus']);
        $this->assertSame([], $result->recentOrders);
        $this->assertSame([], $result->topProducts);
    }

    private function createEmptyDto(): DashboardStatsDto
    {
        return new DashboardStatsDto(
            summary: ['totalRevenue' => 0, 'totalOrders' => 0, 'totalCustomers' => 0, 'totalProducts' => 0, 'averageOrderValue' => 0],
            orders: ['byStatus' => [], 'trend' => [], 'currentPeriod' => 0, 'previousPeriod' => 0, 'changePercent' => 0],
            revenue: ['trend' => [], 'currentPeriod' => 0, 'previousPeriod' => 0, 'changePercent' => 0],
            products: ['byCategory' => [], 'lowStockCount' => 0],
            customers: ['newCustomers' => 0, 'returningCustomers' => 0],
            recentOrders: [],
            topProducts: [],
            period: '7d',
            generatedAt: '2026-02-25 00:00:00'
        );
    }
}
