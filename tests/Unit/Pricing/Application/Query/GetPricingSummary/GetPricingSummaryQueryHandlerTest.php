<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query\GetPricingSummary;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\DTO\Analytics\PricingSummaryDTO;
use App\Pricing\Application\Query\GetPricingSummary\GetPricingSummaryQuery;
use App\Pricing\Application\Query\GetPricingSummary\GetPricingSummaryQueryHandler;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQueryHandler;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Group('pricing')]
final class GetPricingSummaryQueryHandlerTest extends TestCase
{
    private Connection&MockObject $connection;
    private GetPromotionPerformanceQueryHandler&MockObject $promotionHandler;
    private CacheInterface&MockObject $cache;
    private GetPricingSummaryQueryHandler $handler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->promotionHandler = $this->createMock(GetPromotionPerformanceQueryHandler::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->handler = new GetPricingSummaryQueryHandler(
            $this->connection,
            $this->promotionHandler,
            $this->cache,
            new NullLogger(),
        );
    }

    public function testInvokeReturnsCachedSummary(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $dateRange = DateRangeFilter::fromPreset('last_30_days');
        $query = new GetPricingSummaryQuery($tenantId, $dateRange);

        $expectedDto = PricingSummaryDTO::create(
            [
                'total_orders_count' => 100,
                'total_revenue_amount' => 500000,
                'total_revenue_currency' => 'USD',
                'total_discount_amount' => 10000,
                'total_discount_currency' => 'USD',
                'average_order_value' => 5000.0,
                'discount_rate' => 2.0,
                'promotions_active_count' => 3,
                'coupons_used_count' => 15,
            ],
            [],
            $dateRange,
        );

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willReturn($expectedDto);

        $result = ($this->handler)($query);

        self::assertInstanceOf(PricingSummaryDTO::class, $result);
        self::assertSame(100, $result->totalOrdersCount);
    }

    public function testInvokeCallsCacheWithCallback(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $dateRange = new DateRangeFilter(); // No dates
        $query = new GetPricingSummaryQuery($tenantId, $dateRange);

        $summaryData = [
            'total_orders_count' => 50,
            'total_revenue_amount' => 250000,
            'total_revenue_currency' => 'EUR',
            'total_discount_amount' => 5000,
            'total_discount_currency' => 'EUR',
            'average_order_value' => 5000.0,
            'discount_rate' => 2.0,
            'promotions_active_count' => 2,
            'coupons_used_count' => 8,
        ];

        $this->connection
            ->method('fetchAssociative')
            ->willReturn($summaryData);

        $this->promotionHandler
            ->method('__invoke')
            ->willReturn([]);

        // Simulate cache miss: execute the callback
        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = self::createMock(ItemInterface::class);

                return $callback($item);
            });

        $result = ($this->handler)($query);

        self::assertSame(50, $result->totalOrdersCount);
        self::assertSame('EUR', $result->totalRevenueCurrency);
    }

    public function testInvokeWithDateRange(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $dateRange = DateRangeFilter::fromDates(
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
        );
        $query = new GetPricingSummaryQuery($tenantId, $dateRange, 10);

        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'total_orders_count' => 30,
                'total_revenue_amount' => 150000,
                'total_revenue_currency' => 'USD',
                'total_discount_amount' => 3000,
                'total_discount_currency' => 'USD',
                'average_order_value' => 5000.0,
                'discount_rate' => 2.0,
                'promotions_active_count' => 1,
                'coupons_used_count' => 5,
            ]);

        $this->promotionHandler
            ->method('__invoke')
            ->willReturn([]);

        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = self::createMock(ItemInterface::class);

                return $callback($item);
            });

        $result = ($this->handler)($query);

        self::assertSame(30, $result->totalOrdersCount);
    }

    public function testInvokeHandlesQueryException(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $dateRange = new DateRangeFilter();
        $query = new GetPricingSummaryQuery($tenantId, $dateRange);

        $this->connection
            ->method('fetchAssociative')
            ->willThrowException(new \Exception('DB error'));

        $this->promotionHandler
            ->method('__invoke')
            ->willReturn([]);

        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = self::createMock(ItemInterface::class);

                return $callback($item);
            });

        $result = ($this->handler)($query);

        // Should return zeroed-out summary on exception
        self::assertSame(0, $result->totalOrdersCount);
        self::assertSame(0, $result->totalRevenueAmount);
    }

    public function testInvokeHandlesFalseQueryResult(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $dateRange = new DateRangeFilter();
        $query = new GetPricingSummaryQuery($tenantId, $dateRange);

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->promotionHandler
            ->method('__invoke')
            ->willReturn([]);

        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = self::createMock(ItemInterface::class);

                return $callback($item);
            });

        $result = ($this->handler)($query);

        self::assertSame(0, $result->totalOrdersCount);
    }
}
