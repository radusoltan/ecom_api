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

#[CoversClass(HealthCheckService::class)]
final class HealthCheckServiceTest extends TestCase
{
    #[Test]
    public function checkReturnsPassWhenDatabaseIsHealthy(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $connection->method('executeQuery')->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertArrayHasKey('status', $health);
        self::assertArrayHasKey('checks', $health);
        self::assertArrayHasKey('database', $health['checks']);
        self::assertSame('pass', $health['checks']['database']['status']);
    }

    #[Test]
    public function checkReturnsFailWhenDatabaseIsDown(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('fail', $health['status']);
        self::assertSame('fail', $health['checks']['database']['status']);
        self::assertStringContainsString('Connection refused', $health['checks']['database']['output']);
    }

    #[Test]
    public function checkReturnsWarnWhenRedisNotConfigured(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $connection->method('executeQuery')->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('warn', $health['checks']['redis']['status']);
        self::assertStringContainsString('not configured', $health['checks']['redis']['output']);
    }

    #[Test]
    public function checkReturnsWarnWhenRabbitMqNotConfigured(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $connection->method('executeQuery')->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
            rabbitmqUrl: '',
        );

        $health = $service->check();

        self::assertSame('warn', $health['checks']['rabbitmq']['status']);
        self::assertStringContainsString('not configured', $health['checks']['rabbitmq']['output']);
    }

    #[Test]
    public function checkReturnsWarnWhenElasticsearchNotConfigured(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $connection->method('executeQuery')->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
            elasticsearchUrl: '',
        );

        $health = $service->check();

        self::assertSame('warn', $health['checks']['elasticsearch']['status']);
        self::assertStringContainsString('not configured', $health['checks']['elasticsearch']['output']);
    }

    #[Test]
    public function overallStatusIsFailWhenAnyComponentFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')
            ->willThrowException(new \RuntimeException('DB down'));

        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('fail', $health['status']);
    }

    #[Test]
    public function overallStatusIsWarnWhenNoFailButSomeWarn(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);
        $connection->method('executeQuery')->willReturn($result);

        // All external services "not configured" = warn, DB = pass
        $service = new HealthCheckService(
            connection: $connection,
            redis: null,
            logger: new NullLogger(),
            elasticsearchUrl: '',
            rabbitmqUrl: '',
        );

        $health = $service->check();

        // DB passes but redis/rabbitmq/es are all warn
        self::assertSame('warn', $health['status']);
    }
}
