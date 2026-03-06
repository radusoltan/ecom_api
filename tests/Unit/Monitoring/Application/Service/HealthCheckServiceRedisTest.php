<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Application\Service;

use App\Monitoring\Application\Service\HealthCheckService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Additional HealthCheckService tests focused on Redis branch coverage.
 *
 * The main HealthCheckServiceTest already covers the happy path and
 * "not configured" scenarios. This file adds Redis + RabbitMQ edge cases.
 */
#[CoversClass(HealthCheckService::class)]
final class HealthCheckServiceRedisTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $this->connection->method('executeQuery')->willReturn($result);
    }

    // -----------------------------------------------------------------------
    // Redis checks
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsPassWhenRedisPingReturnsTrue(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('ping')->willReturn(true);

        $service = new HealthCheckService(
            connection: $this->connection,
            redis: $redis,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('pass', $health['checks']['redis']['status']);
        self::assertStringContainsString('Connected', $health['checks']['redis']['output']);
    }

    #[Test]
    public function itReturnsPassWhenRedisPingReturnsPongString(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('ping')->willReturn('+PONG');

        $service = new HealthCheckService(
            connection: $this->connection,
            redis: $redis,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('pass', $health['checks']['redis']['status']);
    }

    #[Test]
    public function itReturnsFailWhenRedisPingReturnsUnexpectedValue(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('ping')->willReturn('UNEXPECTED');

        $service = new HealthCheckService(
            connection: $this->connection,
            redis: $redis,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('fail', $health['checks']['redis']['status']);
        self::assertStringContainsString('Unexpected ping response', $health['checks']['redis']['output']);
    }

    #[Test]
    public function itReturnsFailWhenRedisThrowsException(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('ping')->willThrowException(new \RuntimeException('Connection timed out'));

        $service = new HealthCheckService(
            connection: $this->connection,
            redis: $redis,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('fail', $health['checks']['redis']['status']);
        self::assertStringContainsString('Connection timed out', $health['checks']['redis']['output']);
    }

    #[Test]
    public function itSetsOverallStatusToFailWhenRedisCheckFails(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('ping')->willThrowException(new \RuntimeException('Redis down'));

        $service = new HealthCheckService(
            connection: $this->connection,
            redis: $redis,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('fail', $health['status']);
    }

    // -----------------------------------------------------------------------
    // RabbitMQ check: invalid URL
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsWarnWhenRabbitMqUrlIsNotConfigured(): void
    {
        $service = new HealthCheckService(
            connection: $this->connection,
            redis: null,
            logger: new NullLogger(),
            rabbitmqUrl: '',
        );

        $health = $service->check();

        self::assertSame('warn', $health['checks']['rabbitmq']['status']);
    }

    // -----------------------------------------------------------------------
    // check() response structure
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncludesIso8601TimeInEveryCheckResult(): void
    {
        $service = new HealthCheckService(
            connection: $this->connection,
            redis: null,
            logger: new NullLogger(),
        );

        $health = $service->check();

        foreach ($health['checks'] as $name => $check) {
            self::assertArrayHasKey('time', $check, "Check '{$name}' is missing 'time' key");
            // ISO 8601 format check: contains 'T' separator (e.g. "2026-01-01T00:00:00+00:00")
            self::assertStringContainsString('T', $check['time'], "Check '{$name}' time is not ISO 8601");
        }
    }

    #[Test]
    public function itIncludesOutputInEveryCheckResult(): void
    {
        $service = new HealthCheckService(
            connection: $this->connection,
            redis: null,
            logger: new NullLogger(),
        );

        $health = $service->check();

        foreach ($health['checks'] as $name => $check) {
            self::assertArrayHasKey('output', $check, "Check '{$name}' is missing 'output' key");
            self::assertNotEmpty($check['output'], "Check '{$name}' output is unexpectedly empty");
        }
    }

    // -----------------------------------------------------------------------
    // RabbitMQ: invalid URL
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailWhenRabbitMqUrlIsInvalid(): void
    {
        $service = new HealthCheckService(
            connection: $this->connection,
            redis: null,
            logger: new NullLogger(),
            rabbitmqUrl: ':::invalid-url',
        );

        $health = $service->check();

        self::assertSame('fail', $health['checks']['rabbitmq']['status']);
        self::assertSame('Invalid RabbitMQ URL', $health['checks']['rabbitmq']['output']);
    }

    #[Test]
    public function itReturnsFailWhenRabbitMqConnectionRefused(): void
    {
        // Port 1 is almost certainly unreachable and will be refused quickly
        $service = new HealthCheckService(
            connection: $this->connection,
            redis: null,
            logger: new NullLogger(),
            rabbitmqUrl: 'amqp://127.0.0.1:1',
        );

        $health = $service->check();

        self::assertSame('fail', $health['checks']['rabbitmq']['status']);
        self::assertStringContainsString('Connection refused', $health['checks']['rabbitmq']['output']);
    }

    // -----------------------------------------------------------------------
    // Elasticsearch: unreachable
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailWhenElasticsearchIsUnreachable(): void
    {
        $service = new HealthCheckService(
            connection: $this->connection,
            redis: null,
            logger: new NullLogger(),
            elasticsearchUrl: 'http://127.0.0.1:19999',
        );

        $health = $service->check();

        self::assertSame('fail', $health['checks']['elasticsearch']['status']);
        self::assertStringContainsString('Connection failed', $health['checks']['elasticsearch']['output']);
    }
}
