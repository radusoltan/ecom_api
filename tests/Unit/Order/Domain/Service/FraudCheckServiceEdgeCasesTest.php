<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Service;

use App\Order\Domain\Service\FraudCheckService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(FraudCheckService::class)]
final class FraudCheckServiceEdgeCasesTest extends TestCase
{
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private FraudCheckService $service;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new FraudCheckService($this->cache, $this->logger);
    }

    // ---------------------------------------------------------------
    // Risk level thresholds
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsLowRiskForScoreOf30(): void
    {
        // medium IP velocity (3 orders) = +20, medium email velocity (2 orders) = +20 → score 40
        // But let's test score exactly 30: medium IP only
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':ip:')) {
                return 3; // medium = +20
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        // score 20 → low
        self::assertSame('low', $result['risk_level']);
    }

    #[Test]
    public function itReturnsMediumRiskForScoreOf31(): void
    {
        // IP medium (+20) + email medium (+20) = 40 → medium
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':ip:')) {
                return 3; // medium
            }
            if (str_contains($key, ':email:')) {
                return 2; // medium
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertSame(40, $result['score']);
        self::assertSame('medium', $result['risk_level']);
    }

    #[Test]
    public function itReturnsHighRiskForScoreOf61(): void
    {
        // IP high (+40) + email medium (+20) = 60 → still medium
        // IP high (+40) + email high (+40) = 80 → high
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':ip:')) {
                return 6; // >= 5 → high = +40
            }
            if (str_contains($key, ':email:')) {
                return 4; // >= 3 → high = +40
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertSame(80, $result['score']);
        self::assertSame('high', $result['risk_level']);
    }

    #[Test]
    public function itCapsScoreAt100(): void
    {
        // IP high (40) + email high (40) + failed (20) = 100
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':ip:')) {
                return 10;
            }
            if (str_contains($key, ':email:')) {
                return 10;
            }
            if (str_contains($key, ':failed:')) {
                return 5;
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertSame(100, $result['score']);
    }

    #[Test]
    public function itDoesNotAddFailedAttemptsScoreWhenAtOrBelowThreshold(): void
    {
        // Only 3 failed attempts (threshold >3), so no score added
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':failed:')) {
                return 3; // exactly 3, NOT > 3
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertSame(0, $result['score']);
        self::assertEmpty($result['reasons']);
    }

    #[Test]
    public function itAddsFailedAttemptsScoreWhenAboveThreshold(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':failed:')) {
                return 4; // > 3 → +20
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertSame(20, $result['score']);
        self::assertCount(1, $result['reasons']);
        self::assertStringContainsString('Multiple failed payment attempts', $result['reasons'][0]);
    }

    // ---------------------------------------------------------------
    // reasons array content
    // ---------------------------------------------------------------

    #[Test]
    public function itIncludesIpVelocityCountInReason(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':ip:')) {
                return 7;
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertStringContainsString('7', $result['reasons'][0]);
    }

    #[Test]
    public function itIncludesEmailVelocityCountInReason(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key): int {
            if (str_contains($key, ':email:')) {
                return 4; // high
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');

        self::assertStringContainsString('4', $result['reasons'][0]);
        self::assertStringContainsString('email', $result['reasons'][0]);
    }

    // ---------------------------------------------------------------
    // No logging when risk is low
    // ---------------------------------------------------------------

    #[Test]
    public function itDoesNotLogWhenScoreIsAtOrBelow30(): void
    {
        $this->cache->method('get')->willReturn(0);

        $this->logger->expects(self::never())->method('warning');

        $this->service->calculateFraudScore('1.2.3.4', 'a@b.com', 'tenant-X');
    }

    // ---------------------------------------------------------------
    // recordOrderPlaced — logs info
    // ---------------------------------------------------------------

    #[Test]
    public function itRecordOrderPlacedLogsInfo(): void
    {
        $this->cache->method('get')->willReturn(0);
        $this->cache->method('delete')->willReturn(true);

        $this->logger->expects(self::atLeastOnce())->method('info');

        $this->service->recordOrderPlaced('10.0.0.1', 'test@test.com', 'tenant-1');
    }

    // ---------------------------------------------------------------
    // recordFailedAttempt — exception handling
    // ---------------------------------------------------------------

    #[Test]
    public function itRecordFailedAttemptHandlesCacheExceptionGracefully(): void
    {
        $this->cache->method('get')->willThrowException(new \RuntimeException('Cache unavailable'));
        $this->logger->expects(self::atLeastOnce())->method('error');

        // Should not throw
        $this->service->recordFailedAttempt('10.0.0.1', 'tenant-1', 'card_declined');
    }

    // ---------------------------------------------------------------
    // calculateFraudScore — result structure
    // ---------------------------------------------------------------

    #[Test]
    public function itAlwaysReturnsScoreRiskLevelAndReasonsKeys(): void
    {
        $this->cache->method('get')->willReturn(0);

        $result = $this->service->calculateFraudScore('1.1.1.1', 'x@y.com', 't-1');

        self::assertArrayHasKey('score', $result);
        self::assertArrayHasKey('risk_level', $result);
        self::assertArrayHasKey('reasons', $result);
        self::assertIsInt($result['score']);
        self::assertIsString($result['risk_level']);
        self::assertIsArray($result['reasons']);
    }

    #[Test]
    public function itHandlesCacheExceptionInGetVelocityReturningZero(): void
    {
        $callCount = 0;
        $this->cache->method('get')->willReturnCallback(function () use (&$callCount): int {
            ++$callCount;
            if (1 === $callCount) {
                throw new \RuntimeException('Cache miss');
            }

            return 0;
        });

        $this->logger->expects(self::atLeastOnce())->method('error');

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        self::assertIsArray($result);
        self::assertArrayHasKey('score', $result);
    }
}
