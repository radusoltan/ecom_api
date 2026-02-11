<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO\Analytics;

/**
 * Date range filter for analytics queries.
 *
 * Supports common presets (today, yesterday, last_7_days, last_30_days, this_month, last_month)
 * or custom date ranges.
 */
final readonly class DateRangeFilter
{
    public function __construct(
        public ?\DateTimeImmutable $startDate = null,
        public ?\DateTimeImmutable $endDate = null,
        public ?string $preset = null,
    ) {
    }

    /**
     * Create from preset (today, yesterday, last_7_days, last_30_days, this_month, last_month).
     */
    public static function fromPreset(string $preset): self
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return match ($preset) {
            'today' => new self(
                startDate: $now->setTime(0, 0, 0),
                endDate: $now->setTime(23, 59, 59),
                preset: $preset
            ),
            'yesterday' => new self(
                startDate: $now->modify('-1 day')->setTime(0, 0, 0),
                endDate: $now->modify('-1 day')->setTime(23, 59, 59),
                preset: $preset
            ),
            'last_7_days' => new self(
                startDate: $now->modify('-7 days')->setTime(0, 0, 0),
                endDate: $now->setTime(23, 59, 59),
                preset: $preset
            ),
            'last_30_days' => new self(
                startDate: $now->modify('-30 days')->setTime(0, 0, 0),
                endDate: $now->setTime(23, 59, 59),
                preset: $preset
            ),
            'this_month' => new self(
                startDate: new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('UTC')),
                endDate: new \DateTimeImmutable('last day of this month 23:59:59', new \DateTimeZone('UTC')),
                preset: $preset
            ),
            'last_month' => new self(
                startDate: new \DateTimeImmutable('first day of last month 00:00:00', new \DateTimeZone('UTC')),
                endDate: new \DateTimeImmutable('last day of last month 23:59:59', new \DateTimeZone('UTC')),
                preset: $preset
            ),
            default => throw new \InvalidArgumentException("Invalid preset: {$preset}. Valid presets: today, yesterday, last_7_days, last_30_days, this_month, last_month")
        };
    }

    /**
     * Create from custom date range.
     */
    public static function fromDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): self
    {
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date must be before or equal to end date');
        }

        return new self(
            startDate: $startDate,
            endDate: $endDate,
            preset: null
        );
    }

    /**
     * Create default filter (last 30 days).
     */
    public static function default(): self
    {
        return self::fromPreset('last_30_days');
    }

    public function hasStartDate(): bool
    {
        return $this->startDate !== null;
    }

    public function hasEndDate(): bool
    {
        return $this->endDate !== null;
    }

    public function isPreset(): bool
    {
        return $this->preset !== null;
    }

    public function getDays(): int
    {
        if (!$this->hasStartDate() || !$this->hasEndDate()) {
            return 0;
        }

        if (null === $this->startDate || null === $this->endDate) {
            return 0;
        }

        $diff = $this->startDate->diff($this->endDate);

        // $diff->days can be int or false. When false, return 0.
        return false === $diff->days ? 0 : $diff->days;
    }
}
