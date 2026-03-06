<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query\GetPromotionPerformance;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQuery;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQueryHandler;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Group('pricing')]
final class GetPromotionPerformanceQueryHandlerTest extends TestCase
{
    private Connection&MockObject $connection;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;
    private GetPromotionPerformanceQueryHandler $handler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new GetPromotionPerformanceQueryHandler(
            $this->connection,
            $this->cache,
            $this->logger,
        );
    }

    public function testReturnsCachedResults(): void
    {
        $query = new GetPromotionPerformanceQuery(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            DateRangeFilter::default(),
        );

        $this->cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);

            return $callback($item);
        });

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'promotion_id' => 'p1',
                'promotion_name' => 'Summer Sale',
                'promotion_type' => 'percentage',
                'orders_count' => 42,
                'total_revenue_amount' => 500000,
                'total_revenue_currency' => 'USD',
                'total_discount_amount' => 50000,
                'total_discount_currency' => 'USD',
                'average_discount_amount' => 1190.48,
                'conversion_rate' => 15.5,
                'valid_from' => '2026-01-01 00:00:00',
                'valid_to' => '2026-06-30 23:59:59',
            ],
        ]);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertSame('p1', $result[0]->promotionId);
        self::assertSame('Summer Sale', $result[0]->promotionName);
        self::assertSame(42, $result[0]->ordersCount);
    }

    public function testReturnsEmptyOnDbError(): void
    {
        $query = new GetPromotionPerformanceQuery(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            DateRangeFilter::default(),
        );

        $this->cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);

            return $callback($item);
        });

        $this->connection->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->logger->expects(self::once())->method('error');

        $result = ($this->handler)($query);
        self::assertSame([], $result);
    }

    public function testWithSpecificPromotionId(): void
    {
        $query = new GetPromotionPerformanceQuery(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            DateRangeFilter::default(),
            promotionId: 'promo-123',
        );

        $this->cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);

            return $callback($item);
        });

        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = ($this->handler)($query);
        self::assertSame([], $result);
    }

    public function testWithNoDateRange(): void
    {
        $query = new GetPromotionPerformanceQuery(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            new DateRangeFilter(),
        );

        $this->cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);

            return $callback($item);
        });

        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = ($this->handler)($query);
        self::assertSame([], $result);
    }
}
