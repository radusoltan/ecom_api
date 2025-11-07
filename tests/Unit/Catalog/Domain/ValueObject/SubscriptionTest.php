<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\Subscription;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testCreateSubscriptionWithValidData(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );

        $this->assertEquals(SubscriptionInterval::MONTHLY, $subscription->interval());
        $this->assertEquals(12, $subscription->billingCycles());
        $this->assertEquals(0, $subscription->setupFee()->getAmount());
        $this->assertNull($subscription->trialPeriodEnd());
        $this->assertFalse($subscription->isInfinite());
        $this->assertFalse($subscription->hasTrial());
    }

    public function testCreateInfiniteSubscription(): void
    {
        $subscription = Subscription::createInfinite(
            SubscriptionInterval::MONTHLY,
            Money::fromScalars(1000, 'USD')
        );

        $this->assertEquals(0, $subscription->billingCycles());
        $this->assertTrue($subscription->isInfinite());
        $this->assertEquals(1000, $subscription->setupFee()->getAmount());
    }

    public function testCreateSubscriptionWithTrial(): void
    {
        $trialEnd = new \DateTimeImmutable('+14 days');

        $subscription = Subscription::createWithTrial(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(500, 'USD'),
            $trialEnd
        );

        $this->assertTrue($subscription->hasTrial());
        $this->assertTrue($subscription->isTrialActive());
        $this->assertEquals($trialEnd, $subscription->trialPeriodEnd());
    }

    public function testNegativeBillingCyclesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Subscription::create(
            SubscriptionInterval::MONTHLY,
            -1,
            Money::fromScalars(0, 'USD')
        );
    }

    public function testBillingCyclesAbove999ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Subscription::create(
            SubscriptionInterval::MONTHLY,
            1000,
            Money::fromScalars(0, 'USD')
        );
    }

    public function testNegativeSetupFeeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(-100, 'USD')
        );
    }

    public function testTrialEndInPastThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Subscription::createWithTrial(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD'),
            new \DateTimeImmutable('-1 day')
        );
    }

    public function testCalculateNextBillingDateDaily(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::DAILY,
            30,
            Money::fromScalars(0, 'USD')
        );

        $startDate = new \DateTimeImmutable('2025-01-01');
        $nextDate = $subscription->calculateNextBillingDate($startDate);

        $this->assertEquals('2025-01-02', $nextDate->format('Y-m-d'));
    }

    public function testCalculateNextBillingDateWeekly(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::WEEKLY,
            52,
            Money::fromScalars(0, 'USD')
        );

        $startDate = new \DateTimeImmutable('2025-01-01');
        $nextDate = $subscription->calculateNextBillingDate($startDate);

        $this->assertEquals('2025-01-08', $nextDate->format('Y-m-d'));
    }

    public function testCalculateNextBillingDateMonthly(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );

        $startDate = new \DateTimeImmutable('2025-01-15');
        $nextDate = $subscription->calculateNextBillingDate($startDate);

        $this->assertEquals('2025-02-15', $nextDate->format('Y-m-d'));
    }

    public function testCalculateNextBillingDateYearly(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::YEARLY,
            5,
            Money::fromScalars(0, 'USD')
        );

        $startDate = new \DateTimeImmutable('2025-01-01');
        $nextDate = $subscription->calculateNextBillingDate($startDate);

        $this->assertEquals('2026-01-01', $nextDate->format('Y-m-d'));
    }

    public function testPreviewBillingDatesForLimitedSubscription(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::MONTHLY,
            3,
            Money::fromScalars(0, 'USD')
        );

        $startDate = new \DateTimeImmutable('2025-01-01');
        $dates = $subscription->previewBillingDates($startDate, 12);

        // Should only return 3 dates (billing cycles limit)
        $this->assertCount(3, $dates);
        $this->assertEquals('2025-02-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2025-03-01', $dates[1]->format('Y-m-d'));
        $this->assertEquals('2025-04-01', $dates[2]->format('Y-m-d'));
    }

    public function testPreviewBillingDatesForInfiniteSubscription(): void
    {
        $subscription = Subscription::createInfinite(
            SubscriptionInterval::MONTHLY,
            Money::fromScalars(0, 'USD')
        );

        $startDate = new \DateTimeImmutable('2025-01-01');
        $dates = $subscription->previewBillingDates($startDate, 12);

        // Should return all 12 requested dates
        $this->assertCount(12, $dates);
        $this->assertEquals('2025-02-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2026-01-01', $dates[11]->format('Y-m-d'));
    }

    public function testPreviewBillingDatesWithActiveTrial(): void
    {
        $now = new \DateTimeImmutable();
        $trialEnd = $now->modify('+14 days');

        $subscription = Subscription::createWithTrial(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD'),
            $trialEnd
        );

        $dates = $subscription->previewBillingDates($now, 3);

        // Should have 3 billing dates
        $this->assertCount(3, $dates);
        // First billing should be after trial end
        $this->assertGreaterThan($trialEnd, $dates[0]);
    }

    public function testHasSetupFee(): void
    {
        $withFee = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(1000, 'USD')
        );

        $withoutFee = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(0, 'USD')
        );

        $this->assertTrue($withFee->hasSetupFee());
        $this->assertFalse($withoutFee->hasSetupFee());
    }

    public function testEquals(): void
    {
        $sub1 = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(500, 'USD')
        );

        $sub2 = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(500, 'USD')
        );

        $sub3 = Subscription::create(
            SubscriptionInterval::YEARLY,
            12,
            Money::fromScalars(500, 'USD')
        );

        $this->assertTrue($sub1->equals($sub2));
        $this->assertFalse($sub1->equals($sub3));
    }

    public function testToArray(): void
    {
        $subscription = Subscription::create(
            SubscriptionInterval::MONTHLY,
            12,
            Money::fromScalars(1000, 'USD')
        );

        $array = $subscription->toArray();

        $this->assertEquals('monthly', $array['interval']);
        $this->assertEquals(12, $array['billing_cycles']);
        $this->assertEquals(1000, $array['setup_fee']['amount']);
        $this->assertEquals('USD', $array['setup_fee']['currency']);
        $this->assertFalse($array['is_infinite']);
        $this->assertFalse($array['has_trial']);
        $this->assertFalse($array['is_trial_active']);
    }
}
