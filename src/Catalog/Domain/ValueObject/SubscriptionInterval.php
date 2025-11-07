<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Subscription interval enum for recurring billing periods.
 *
 * Business Rules:
 * - interval: Must be one of: daily, weekly, monthly, yearly
 * - Used for subscription-based products
 */
enum SubscriptionInterval: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    /**
     * Get all available intervals.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $interval): string => $interval->value,
            self::cases()
        );
    }

    /**
     * Create from string value with validation.
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    /**
     * Check if interval is daily.
     */
    public function isDaily(): bool
    {
        return $this === self::DAILY;
    }

    /**
     * Check if interval is weekly.
     */
    public function isWeekly(): bool
    {
        return $this === self::WEEKLY;
    }

    /**
     * Check if interval is monthly.
     */
    public function isMonthly(): bool
    {
        return $this === self::MONTHLY;
    }

    /**
     * Check if interval is yearly.
     */
    public function isYearly(): bool
    {
        return $this === self::YEARLY;
    }

    /**
     * Get human-readable label for the interval.
     */
    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::YEARLY => 'Yearly',
        };
    }

    /**
     * Get the number of days for this interval (approximate).
     */
    public function approximateDays(): int
    {
        return match ($this) {
            self::DAILY => 1,
            self::WEEKLY => 7,
            self::MONTHLY => 30,
            self::YEARLY => 365,
        };
    }
}
