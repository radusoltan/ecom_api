<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monitoring\Domain\Event;

use App\Monitoring\Domain\Event\AlertResolved;
use App\Monitoring\Domain\Event\AlertTriggered;
use App\Monitoring\Domain\Event\ThresholdBreached;
use App\Shared\Domain\Event\DomainEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertTriggered::class)]
#[CoversClass(AlertResolved::class)]
#[CoversClass(ThresholdBreached::class)]
final class MonitoringEventsTest extends TestCase
{
    #[Test]
    public function alertTriggeredCarriesAllData(): void
    {
        $now = new \DateTimeImmutable();
        $event = new AlertTriggered(
            metric: 'api_response_time',
            value: 350.0,
            severity: 'warning',
            message: 'API response exceeded 200ms threshold',
            occurredOn: $now,
        );

        self::assertInstanceOf(DomainEvent::class, $event);
        self::assertSame('api_response_time', $event->metric);
        self::assertSame(350.0, $event->value);
        self::assertSame('warning', $event->severity);
        self::assertSame('API response exceeded 200ms threshold', $event->message);
        self::assertSame($now, $event->occurredOn());
    }

    #[Test]
    public function alertResolvedCarriesAllData(): void
    {
        $now = new \DateTimeImmutable();
        $event = new AlertResolved(
            metric: 'database_query_time',
            severity: 'critical',
            occurredOn: $now,
        );

        self::assertInstanceOf(DomainEvent::class, $event);
        self::assertSame('database_query_time', $event->metric);
        self::assertSame('critical', $event->severity);
        self::assertSame($now, $event->occurredOn());
    }

    #[Test]
    public function thresholdBreachedCarriesAllData(): void
    {
        $now = new \DateTimeImmutable();
        $event = new ThresholdBreached(
            metric: 'cache_hit_rate',
            currentValue: 65.0,
            thresholdValue: 80.0,
            severity: 'warning',
            occurredOn: $now,
        );

        self::assertInstanceOf(DomainEvent::class, $event);
        self::assertSame('cache_hit_rate', $event->metric);
        self::assertSame(65.0, $event->currentValue);
        self::assertSame(80.0, $event->thresholdValue);
        self::assertSame('warning', $event->severity);
        self::assertSame($now, $event->occurredOn());
    }
}
