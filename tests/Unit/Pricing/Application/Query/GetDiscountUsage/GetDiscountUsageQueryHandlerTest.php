<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query\GetDiscountUsage;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\DTO\Analytics\DiscountUsageDTO;
use App\Pricing\Application\Query\GetDiscountUsage\GetDiscountUsageQuery;
use App\Pricing\Application\Query\GetDiscountUsage\GetDiscountUsageQueryHandler;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GetDiscountUsageQueryHandlerTest extends TestCase
{
    private Connection $connection;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private GetDiscountUsageQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new GetDiscountUsageQueryHandler(
            $this->connection,
            $this->cache,
            $this->logger,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testHandleReturnsDiscountUsageDto(): void
    {
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: new DateRangeFilter(),
            topCouponsLimit: 10,
        );

        $summaryData = [
            'total_coupons_used' => 5,
            'total_discount_amount' => 5000,
            'total_discount_currency' => 'USD',
            'average_discount_per_order' => 1000.0,
            'active_coupons_count' => 3,
            'expired_coupons_count' => 2,
        ];

        $topCouponsData = [
            [
                'coupon_code' => 'SAVE10',
                'promotion_name' => '10% Off',
                'usage_count' => 3,
                'total_discount' => 3000,
                'currency' => 'USD',
            ],
        ];

        $unusedPromotionsData = [
            [
                'id' => 'some-uuid',
                'name' => 'Unused Promo',
                'type' => 'coupon',
                'coupon_code' => 'UNUSED',
                'valid_from' => null,
                'valid_to' => null,
            ],
        ];

        // The cache calls the callback directly
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);

                return $callback($item);
            });

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn($summaryData);

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($topCouponsData, $unusedPromotionsData);

        $result = ($this->handler)($query);

        $this->assertInstanceOf(DiscountUsageDTO::class, $result);
        $this->assertSame(5, $result->totalCouponsUsed);
        $this->assertSame(5000, $result->totalDiscountAmount);
        $this->assertSame('USD', $result->totalDiscountCurrency);
        $this->assertSame(1000.0, $result->averageDiscountPerOrder);
        $this->assertSame(3, $result->activeCouponsCount);
        $this->assertSame(2, $result->expiredCouponsCount);
        $this->assertCount(1, $result->topCoupons);
        $this->assertCount(1, $result->unusedPromotions);
    }

    public function testHandleUsesCacheKeyBasedOnQueryParameters(): void
    {
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: new DateRangeFilter(),
            topCouponsLimit: 5,
        );

        $capturedKey = null;
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use (&$capturedKey) {
                $capturedKey = $key;
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                // Return minimal DTO from callback
                $this->connection->method('fetchAssociative')->willReturn([
                    'total_coupons_used' => 0,
                    'total_discount_amount' => 0,
                    'total_discount_currency' => 'USD',
                    'average_discount_per_order' => 0,
                    'active_coupons_count' => 0,
                    'expired_coupons_count' => 0,
                ]);
                $this->connection->method('fetchAllAssociative')->willReturn([]);

                return $callback($item);
            });

        ($this->handler)($query);

        $this->assertNotNull($capturedKey);
        $this->assertStringContainsString($this->tenantId->toString(), $capturedKey);
        $this->assertStringContainsString('pricing_analytics.discount_usage', $capturedKey);
        $this->assertStringContainsString('5', $capturedKey);
    }

    public function testHandleWithDateRangeIncludesDatesInCacheKey(): void
    {
        $startDate = new \DateTimeImmutable('2026-01-01');
        $endDate = new \DateTimeImmutable('2026-01-31');
        $dateRange = DateRangeFilter::fromDates($startDate, $endDate);

        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: $dateRange,
            topCouponsLimit: 10,
        );

        $capturedKey = null;
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use (&$capturedKey) {
                $capturedKey = $key;
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                $this->connection->method('fetchAssociative')->willReturn([
                    'total_coupons_used' => 0,
                    'total_discount_amount' => 0,
                    'total_discount_currency' => 'USD',
                    'average_discount_per_order' => 0,
                    'active_coupons_count' => 0,
                    'expired_coupons_count' => 0,
                ]);
                $this->connection->method('fetchAllAssociative')->willReturn([]);

                return $callback($item);
            });

        ($this->handler)($query);

        $this->assertStringContainsString('2026-01-01', $capturedKey);
        $this->assertStringContainsString('2026-01-31', $capturedKey);
    }

    public function testHandleReturnsZeroSummaryWhenDbReturnsNoRows(): void
    {
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: new DateRangeFilter(),
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                return $callback($item);
            });

        // fetchAssociative returns false (no rows)
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $result = ($this->handler)($query);

        $this->assertSame(0, $result->totalCouponsUsed);
        $this->assertSame(0, $result->totalDiscountAmount);
        $this->assertSame('USD', $result->totalDiscountCurrency);
        $this->assertSame(0, $result->activeCouponsCount);
        $this->assertSame(0, $result->expiredCouponsCount);
        $this->assertEmpty($result->topCoupons);
        $this->assertEmpty($result->unusedPromotions);
    }

    public function testHandleReturnsZeroSummaryWhenDbThrowsOnSummaryQuery(): void
    {
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: new DateRangeFilter(),
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                return $callback($item);
            });

        $this->connection
            ->method('fetchAssociative')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        $result = ($this->handler)($query);

        $this->assertSame(0, $result->totalCouponsUsed);
        $this->assertSame(0, $result->totalDiscountAmount);
        $this->assertSame('USD', $result->totalDiscountCurrency);
    }

    public function testHandleReturnsEmptyTopCouponsWhenDbThrowsOnCouponsQuery(): void
    {
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: new DateRangeFilter(),
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');

                return $callback($item);
            });

        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'total_coupons_used' => 3,
                'total_discount_amount' => 3000,
                'total_discount_currency' => 'USD',
                'average_discount_per_order' => 1000.0,
                'active_coupons_count' => 2,
                'expired_coupons_count' => 1,
            ]);

        $this->connection
            ->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('Query failed'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        $result = ($this->handler)($query);

        $this->assertSame(3, $result->totalCouponsUsed);
        $this->assertEmpty($result->topCoupons);
        $this->assertEmpty($result->unusedPromotions);
    }

    public function testHandleCacheIsUsedReturningExistingDto(): void
    {
        $query = new GetDiscountUsageQuery(
            tenantId: $this->tenantId,
            dateRange: new DateRangeFilter(),
        );

        $cachedDto = new DiscountUsageDTO(
            totalCouponsUsed: 7,
            totalDiscountAmount: 7000,
            totalDiscountCurrency: 'EUR',
            averageDiscountPerOrder: 1000.0,
            topCoupons: [],
            unusedPromotions: [],
            activeCouponsCount: 4,
            expiredCouponsCount: 3,
        );

        // Cache returns the DTO directly (already cached)
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn($cachedDto);

        // DB should NOT be called
        $this->connection->expects($this->never())->method('fetchAssociative');
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $result = ($this->handler)($query);

        $this->assertSame($cachedDto, $result);
        $this->assertSame(7, $result->totalCouponsUsed);
        $this->assertSame('EUR', $result->totalDiscountCurrency);
    }
}
