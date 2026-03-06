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
final class FraudCheckServiceTest extends TestCase
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

    #[Test]
    public function itReturnsLowRiskForCleanOrder(): void
    {
        // All velocity counters return 0
        $this->cache->method('get')->willReturn(0);

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        self::assertSame(0, $result['score']);
        self::assertSame('low', $result['risk_level']);
        self::assertEmpty($result['reasons']);
    }

    #[Test]
    public function itDetectsHighIpVelocity(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key) {
            // Return 5 for IP velocity, 0 for email velocity, 0 for failed attempts
            if (str_contains($key, ':ip:')) {
                return 5;
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        self::assertSame(40, $result['score']);
        self::assertSame('medium', $result['risk_level']);
        self::assertNotEmpty($result['reasons']);
        self::assertStringContainsString('High order velocity from IP', $result['reasons'][0]);
    }

    #[Test]
    public function itDetectsHighEmailVelocity(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key) {
            if (str_contains($key, ':email:')) {
                return 3;
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        self::assertSame(40, $result['score']);
        self::assertSame('medium', $result['risk_level']);
        self::assertStringContainsString('High order velocity from email', $result['reasons'][0]);
    }

    #[Test]
    public function itDetectsMediumIpVelocity(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key) {
            if (str_contains($key, ':ip:')) {
                return 3;
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        self::assertSame(20, $result['score']);
        self::assertSame('low', $result['risk_level']);
    }

    #[Test]
    public function itDetectsHighRiskWithMultipleFactors(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key) {
            if (str_contains($key, ':ip:')) {
                return 6; // >5 = high
            }
            if (str_contains($key, ':email:')) {
                return 4; // >3 = high
            }
            if (str_contains($key, ':failed:')) {
                return 5; // >3 = adds 20
            }

            return 0;
        });

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        self::assertSame(100, $result['score']); // 40+40+20 capped at 100
        self::assertSame('high', $result['risk_level']);
        self::assertCount(3, $result['reasons']);
    }

    #[Test]
    public function itLogsWarningForSuspiciousActivity(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key) {
            if (str_contains($key, ':ip:')) {
                return 5;
            }

            return 0;
        });

        $this->logger->expects(self::once())->method('warning')
            ->with('Fraud check detected suspicious activity', self::anything());

        $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');
    }

    #[Test]
    public function itRecordsOrderPlaced(): void
    {
        $this->cache->expects(self::atLeastOnce())->method('get');
        $this->cache->expects(self::atLeastOnce())->method('delete');

        $this->service->recordOrderPlaced('192.168.1.1', 'user@example.com', 'tenant-1');
    }

    #[Test]
    public function itRecordsFailedAttempt(): void
    {
        $this->cache->method('get')->willReturn(0);
        $this->cache->method('delete')->willReturn(true);

        $this->logger->expects(self::atLeastOnce())->method('info');

        $this->service->recordFailedAttempt('192.168.1.1', 'tenant-1', 'payment_declined');
    }

    #[Test]
    public function itHandlesCacheExceptionGracefully(): void
    {
        $this->cache->method('get')->willThrowException(new \RuntimeException('Cache down'));

        $this->logger->expects(self::atLeastOnce())->method('error');

        $result = $this->service->calculateFraudScore('192.168.1.1', 'user@example.com', 'tenant-1');

        // Should return low risk when cache is unavailable
        self::assertSame(0, $result['score']);
        self::assertSame('low', $result['risk_level']);
    }
}
