<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\DTO\Analytics;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use PHPUnit\Framework\TestCase;

final class DateRangeFilterTest extends TestCase
{
    public function testFromPresetToday(): void
    {
        $filter = DateRangeFilter::fromPreset('today');

        $this->assertNotNull($filter->startDate);
        $this->assertNotNull($filter->endDate);
        $this->assertSame('today', $filter->preset);
        $this->assertTrue($filter->isPreset());
        $this->assertTrue($filter->hasStartDate());
        $this->assertTrue($filter->hasEndDate());

        // Verify it's today
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->assertSame($now->format('Y-m-d'), $filter->startDate->format('Y-m-d'));
        $this->assertSame($now->format('Y-m-d'), $filter->endDate->format('Y-m-d'));
    }

    public function testFromPresetYesterday(): void
    {
        $filter = DateRangeFilter::fromPreset('yesterday');

        $this->assertSame('yesterday', $filter->preset);

        // Verify it's yesterday
        $yesterday = new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC'));
        $this->assertSame($yesterday->format('Y-m-d'), $filter->startDate->format('Y-m-d'));
        $this->assertSame($yesterday->format('Y-m-d'), $filter->endDate->format('Y-m-d'));
    }

    public function testFromPresetLast7Days(): void
    {
        $filter = DateRangeFilter::fromPreset('last_7_days');

        $this->assertSame('last_7_days', $filter->preset);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sevenDaysAgo = $now->modify('-7 days');

        $this->assertSame($sevenDaysAgo->format('Y-m-d'), $filter->startDate->format('Y-m-d'));
        $this->assertSame($now->format('Y-m-d'), $filter->endDate->format('Y-m-d'));
    }

    public function testFromPresetLast30Days(): void
    {
        $filter = DateRangeFilter::fromPreset('last_30_days');

        $this->assertSame('last_30_days', $filter->preset);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $thirtyDaysAgo = $now->modify('-30 days');

        $this->assertSame($thirtyDaysAgo->format('Y-m-d'), $filter->startDate->format('Y-m-d'));
        $this->assertSame($now->format('Y-m-d'), $filter->endDate->format('Y-m-d'));
    }

    public function testFromPresetThisMonth(): void
    {
        $filter = DateRangeFilter::fromPreset('this_month');

        $this->assertSame('this_month', $filter->preset);

        $firstDay = new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('UTC'));
        $lastDay = new \DateTimeImmutable('last day of this month 23:59:59', new \DateTimeZone('UTC'));

        $this->assertSame($firstDay->format('Y-m-d'), $filter->startDate->format('Y-m-d'));
        $this->assertSame($lastDay->format('Y-m-d'), $filter->endDate->format('Y-m-d'));
    }

    public function testFromPresetLastMonth(): void
    {
        $filter = DateRangeFilter::fromPreset('last_month');

        $this->assertSame('last_month', $filter->preset);

        $firstDay = new \DateTimeImmutable('first day of last month 00:00:00', new \DateTimeZone('UTC'));
        $lastDay = new \DateTimeImmutable('last day of last month 23:59:59', new \DateTimeZone('UTC'));

        $this->assertSame($firstDay->format('Y-m-d'), $filter->startDate->format('Y-m-d'));
        $this->assertSame($lastDay->format('Y-m-d'), $filter->endDate->format('Y-m-d'));
    }

    public function testFromPresetInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid preset: invalid');

        DateRangeFilter::fromPreset('invalid');
    }

    public function testFromDates(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01', new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable('2025-01-31', new \DateTimeZone('UTC'));

        $filter = DateRangeFilter::fromDates($startDate, $endDate);

        $this->assertSame($startDate, $filter->startDate);
        $this->assertSame($endDate, $filter->endDate);
        $this->assertNull($filter->preset);
        $this->assertFalse($filter->isPreset());
        $this->assertTrue($filter->hasStartDate());
        $this->assertTrue($filter->hasEndDate());
    }

    public function testFromDatesInvalidOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start date must be before or equal to end date');

        $startDate = new \DateTimeImmutable('2025-01-31', new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable('2025-01-01', new \DateTimeZone('UTC'));

        DateRangeFilter::fromDates($startDate, $endDate);
    }

    public function testFromDatesSameDay(): void
    {
        $date = new \DateTimeImmutable('2025-01-15', new \DateTimeZone('UTC'));

        $filter = DateRangeFilter::fromDates($date, $date);

        $this->assertSame($date, $filter->startDate);
        $this->assertSame($date, $filter->endDate);
        $this->assertSame(0, $filter->getDays());
    }

    public function testDefault(): void
    {
        $filter = DateRangeFilter::default();

        $this->assertSame('last_30_days', $filter->preset);
        $this->assertTrue($filter->isPreset());
    }

    public function testGetDays(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01', new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable('2025-01-31', new \DateTimeZone('UTC'));

        $filter = DateRangeFilter::fromDates($startDate, $endDate);

        $this->assertSame(30, $filter->getDays());
    }

    public function testGetDaysWithNullDates(): void
    {
        $filter = new DateRangeFilter(null, null, null);

        $this->assertSame(0, $filter->getDays());
        $this->assertFalse($filter->hasStartDate());
        $this->assertFalse($filter->hasEndDate());
    }

    public function testHasStartDate(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01', new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable('2025-01-31', new \DateTimeZone('UTC'));

        $filter = DateRangeFilter::fromDates($startDate, $endDate);
        $this->assertTrue($filter->hasStartDate());

        $emptyFilter = new DateRangeFilter(null, $endDate, null);
        $this->assertFalse($emptyFilter->hasStartDate());
    }

    public function testHasEndDate(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01', new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable('2025-01-31', new \DateTimeZone('UTC'));

        $filter = DateRangeFilter::fromDates($startDate, $endDate);
        $this->assertTrue($filter->hasEndDate());

        $emptyFilter = new DateRangeFilter($startDate, null, null);
        $this->assertFalse($emptyFilter->hasEndDate());
    }
}
