<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;
use Webmozart\Assert\Assert;

/**
 * Subscription configuration value object.
 *
 * Business Rules:
 * - interval: Must be valid SubscriptionInterval (daily, weekly, monthly, yearly)
 * - billing_cycles: 0 = infinite, 1-999 = limited cycles
 * - setup_fee: Optional one-time fee, must be >= 0
 * - trial_period_end: Optional trial end date, must be in the future
 */
final readonly class Subscription
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private SubscriptionInterval $interval,
        private int $billingCycles,
        private Money $setupFee,
        private ?\DateTimeImmutable $trialPeriodEnd = null,
    ) {
        Assert::greaterThanEq(
            $this->billingCycles,
            0,
            'Billing cycles must be 0 (infinite) or positive number'
        );

        Assert::lessThanEq(
            $this->billingCycles,
            999,
            'Billing cycles cannot exceed 999'
        );

        Assert::greaterThanEq(
            $this->setupFee->getAmount(),
            0,
            'Setup fee must be positive or zero'
        );

        if (null !== $this->trialPeriodEnd) {
            Assert::greaterThan(
                $this->trialPeriodEnd,
                new \DateTimeImmutable(),
                'Trial period end must be in the future'
            );
        }
    }

    /**
     * Create subscription with no trial period.
     */
    public static function create(
        SubscriptionInterval $interval,
        int $billingCycles,
        Money $setupFee,
    ): self {
        return new self($interval, $billingCycles, $setupFee, null);
    }

    /**
     * Create subscription with trial period.
     */
    public static function createWithTrial(
        SubscriptionInterval $interval,
        int $billingCycles,
        Money $setupFee,
        \DateTimeImmutable $trialPeriodEnd,
    ): self {
        return new self($interval, $billingCycles, $setupFee, $trialPeriodEnd);
    }

    /**
     * Create infinite subscription (billing cycles = 0).
     */
    public static function createInfinite(
        SubscriptionInterval $interval,
        Money $setupFee,
    ): self {
        return new self($interval, 0, $setupFee, null);
    }

    public function interval(): SubscriptionInterval
    {
        return $this->interval;
    }

    public function billingCycles(): int
    {
        return $this->billingCycles;
    }

    public function setupFee(): Money
    {
        return $this->setupFee;
    }

    public function trialPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->trialPeriodEnd;
    }

    /**
     * Check if subscription is infinite (never ends).
     */
    public function isInfinite(): bool
    {
        return 0 === $this->billingCycles;
    }

    /**
     * Check if subscription has a trial period.
     */
    public function hasTrial(): bool
    {
        return null !== $this->trialPeriodEnd;
    }

    /**
     * Check if trial period is currently active.
     */
    public function isTrialActive(): bool
    {
        if (null === $this->trialPeriodEnd) {
            return false;
        }

        return $this->trialPeriodEnd > new \DateTimeImmutable();
    }

    /**
     * Check if subscription has setup fee.
     */
    public function hasSetupFee(): bool
    {
        return $this->setupFee->getAmount() > 0;
    }

    /**
     * Calculate next billing date from a given start date.
     */
    public function calculateNextBillingDate(\DateTimeImmutable $fromDate): \DateTimeImmutable
    {
        return match ($this->interval) {
            SubscriptionInterval::DAILY => $fromDate->modify('+1 day'),
            SubscriptionInterval::WEEKLY => $fromDate->modify('+1 week'),
            SubscriptionInterval::MONTHLY => $fromDate->modify('+1 month'),
            SubscriptionInterval::YEARLY => $fromDate->modify('+1 year'),
        };
    }

    /**
     * Preview next N billing dates.
     *
     * @return array<\DateTimeImmutable>
     */
    public function previewBillingDates(\DateTimeImmutable $startDate, int $count = 12): array
    {
        Assert::greaterThan($count, 0, 'Count must be positive');
        Assert::lessThanEq($count, 100, 'Cannot preview more than 100 billing dates');

        $dates = [];
        $currentDate = $startDate;

        // If trial period exists, start billing after trial ends
        if ($this->hasTrial() && $this->isTrialActive() && null !== $this->trialPeriodEnd) {
            $currentDate = $this->trialPeriodEnd;
        }

        $maxDates = $this->isInfinite() ? $count : min($count, $this->billingCycles);

        for ($i = 0; $i < $maxDates; ++$i) {
            $currentDate = $this->calculateNextBillingDate($currentDate);
            $dates[] = $currentDate;
        }

        return $dates;
    }

    /**
     * Check equality with another subscription.
     */
    public function equals(self $other): bool
    {
        return $this->interval === $other->interval
            && $this->billingCycles === $other->billingCycles
            && $this->setupFee->equals($other->setupFee)
            && $this->trialPeriodEnd == $other->trialPeriodEnd;
    }

    /**
     * Get array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'interval' => $this->interval->value,
            'billing_cycles' => $this->billingCycles,
            'setup_fee' => [
                'amount' => $this->setupFee->getAmount(),
                'currency' => $this->setupFee->getCurrency(),
            ],
            'trial_period_end' => $this->trialPeriodEnd?->format('Y-m-d H:i:s'),
            'is_infinite' => $this->isInfinite(),
            'has_trial' => $this->hasTrial(),
            'is_trial_active' => $this->isTrialActive(),
        ];
    }
}
